<?php

declare(strict_types=1);

namespace DR\Review\Ai\Agent\ReviewSummary;

use DR\Review\Ai\Summary\AiSummaryResponse;
use DR\Review\Ai\Targetproccess\TargetprocessAPI;
use DR\Review\Entity\Git\Diff\DiffFile;
use DR\Review\Entity\Review\CodeReview;
use DR\Utils\Assert;
use OpenAI\Client;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

class ReviewSummaryAgent
{
    private string $systemInstructions;
    private int $maxTokens;

    public function __construct(
        private readonly Client $openAIClient,
        private ParameterBagInterface $params,
        private readonly TargetprocessAPI $targetProcessApi
    ) {
        $this->setSystemInstructions();
        $this->maxTokens = $this->params->has('AI_MAX_TOKENS') ? $this->params->get('AI_MAX_TOKENS') : 4000;
    }

    /**
     * Generates a summary for the given diff using the AI model.
     * Note: Token validation should be done before calling this method.
     */
    public function generateSummary(string $diff, ?CodeReview $review): AiSummaryResponse
    {
        $model = $this->getConfiguredModel();

        // insert Targetproccess context from api.
        $targetprocessData = $this->targetProcessApi->getTasksAndStoriesFromCodeReview($review);
        dump($targetprocessData);

        $context = [
            ['role' => 'system', 'content' => $this->systemInstructions],
            ['role' => 'user', 'content' => $diff],
        ];

        try {
            $openAIResponse = $this->openAIClient->chat()->create([
                'model' => $model,
                'messages' => $context,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Failed to generate summary: ' . $e->getMessage() . ' Context' . print_r($context, true),
                0,
                $e
            );
        }

        return new AiSummaryResponse(Assert::notNull($openAIResponse->choices[0]->message->content), $context);
    }

    /**
     * Gets the system instructions.
     */
    public function getSystemInstructions(): string
    {
        return $this->systemInstructions;
    }

    /**
     * Validate that the diff files don't exceed token limits and filter out oversized files.
     *
     * @param DiffFile[] $diffFiles     The diff files to validate
     * @param int|null   $maxFileTokens Maximum tokens per file (if null, uses 25% of total max tokens)
     *
     * @return array{files: DiffFile[], tokenSizes: array<string, int>, filteredFiles: array<int, array{file: string, tokens: int, reason: string}>}
     *                      Filtered files, their token sizes, and excluded files
     * @throws RuntimeException If no files remain after filtering or total exceeds limits
     */
    public function validateTokenCountForFiles(array $diffFiles, ?int $maxFileTokens = null): array
    {
        $maxFileTokens  = $maxFileTokens ?? (int)($this->maxTokens * 0.25);
        $filteredFiles  = [];
        $fileTokenSizes = [];
        $totalTokens    = $this->estimateTokenCountForContent($this->systemInstructions);
        $excludedFiles  = [];

        // First, calculate token sizes for all files and sort by size descending
        $filesWithTokens = [];
        foreach ($diffFiles as $file) {
            $filename    = $file->getPathname();
            $fileContent = $file->rawContent;
            $fileTokens  = $this->estimateTokenCountForContent($fileContent);

            $filesWithTokens[] = [
                'file'     => $file,
                'filename' => $filename,
                'tokens'   => $fileTokens
            ];
        }

        // Sort files by token count ascending (smallest first)
        usort($filesWithTokens, function ($a, $b) {
            return $a['tokens'] <=> $b['tokens'];
        });

        // Now process files in order of smallest to largest
        foreach ($filesWithTokens as $fileData) {
            $file       = $fileData['file'];
            $filename   = $fileData['filename'];
            $fileTokens = $fileData['tokens'];

            if ($fileTokens > $maxFileTokens) {
                $excludedFiles[] = [
                    'file'   => $filename,
                    'tokens' => $fileTokens,
                    'reason' => 'File exceeds maximum tokens per file'
                ];
                continue;
            }

            // Check if adding this file would exceed total token limit
            if ($totalTokens + $fileTokens > $this->maxTokens) {
                $excludedFiles[] = [
                    'file'   => $filename,
                    'tokens' => $fileTokens,
                    'reason' => 'Adding file would exceed total token limit'
                ];
                continue;
            }

            $filteredFiles[]           = $file;
            $fileTokenSizes[$filename] = $fileTokens;
            $totalTokens               += $fileTokens;
        }

        // Log excluded files if any
        if (count($excludedFiles) > 0) {
            // TODO: Replace with proper logger injection when logging is needed
            // For now, we'll skip logging to avoid debug statement warnings
        }

        // Ensure we have at least one file to process
        if (count($filteredFiles) === 0) {
            throw new RuntimeException(
                sprintf(
                    'No files remain after token filtering. All %d files exceeded limits. Consider increasing max tokens or reducing file sizes.',
                    count($diffFiles)
                )
            );
        }

        // Sort the final fileTokenSizes array by token count descending for consistent output
        arsort($fileTokenSizes);

        return [
            'files'         => $filteredFiles,
            'tokenSizes'    => $fileTokenSizes,
            'filteredFiles' => $excludedFiles
        ];
    }

    /**
     * Prepare diff output from array of DiffFile objects.
     *
     * @param DiffFile[] $diffFiles
     */
    public function prepareDiffOutput(array $diffFiles): string
    {
        return implode("\n", array_map(static fn($file) => $file->rawContent, $diffFiles));
    }

    /**
     * Estimate token count for a single content string.
     */
    public function estimateTokenCountForContent(string $content): int
    {
        $model = $this->getConfiguredModel();

        // Add message overhead for user content
        $totalCharacters = $this->getMessageOverhead($model) + strlen($content);

        // Convert characters to estimated tokens
        $estimatedTokens = (int)ceil($totalCharacters / $this->getCharactersPerToken($model));

        // Add safety buffer
        $safetyBuffer = $this->getSafetyBuffer($model);

        return (int)ceil($estimatedTokens * $safetyBuffer);
    }

    /**
     * Get the configured AI model.
     */
    public function getConfiguredModel(): string
    {
        return $this->params->has('AI_MODEL') ? $this->params->get('AI_MODEL') : 'gpt-3.5-turbo';
    }

    /**
     * Get the maximum tokens limit.
     */
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * Get the approximate characters per token for different models.
     */
    private function getCharactersPerToken(string $model): float
    {
        return match (true) {
            str_starts_with($model, 'gpt-3.5') => 4.0,
            str_starts_with($model, 'gpt-4o')  => 3.8, // Slightly more efficient tokenization
            str_starts_with($model, 'gpt-4')   => 4.0,
            str_starts_with($model, 'gpt-5')   => 3.8, // Assuming similar to GPT-4o
            default                            => 4.0, // Conservative default
        };
    }

    /**
     * Get message overhead (tokens for structure) per model.
     */
    private function getMessageOverhead(string $model): int
    {
        return match (true) {
            str_starts_with($model, 'gpt-3.5') => 10,
            str_starts_with($model, 'gpt-4')   => 12, // Slightly more overhead for newer models
            str_starts_with($model, 'gpt-5')   => 12,
            default                            => 10,
        };
    }

    /**
     * Get safety buffer multiplier per model.
     */
    private function getSafetyBuffer(string $model): float
    {
        return match (true) {
            str_starts_with($model, 'gpt-3.5') => 1.1, // 10% buffer
            str_starts_with($model, 'gpt-4')   => 1.15, // 15% buffer (more conservative)
            str_starts_with($model, 'gpt-5')   => 1.15,
            default                            => 1.2, // 20% buffer for unknown models
        };
    }

    /**
     * Sets the system instructions from the prompts/review-summary.md file.
     */
    private function setSystemInstructions(): void
    {
        $projectDir = $this->params->has('kernel.project_dir') ? $this->params->get('kernel.project_dir') : '';
        if ($projectDir === '') {
            $this->systemInstructions = '';

            return;
        }

        $filePath    = $projectDir . '/prompts/review-summary.md';
        $fileContent = file_get_contents($filePath);
        $this->systemInstructions = $fileContent !== false ? $fileContent : '';
    }
}
