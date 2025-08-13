<?php

declare(strict_types=1);

namespace DR\Review\Ai\Summary;

readonly class AiSummaryResponse
{
    /**
     * @param string $summary The AI-generated summary
     * @param array<int, array{role: string, content: string}> $context The context used for the AI request
     * @param array<string, int> $fileTokenSizes Token count per file (filename => token count)
     */
    public function __construct(public string $summary, public array $context = [], public array $fileTokenSizes = [])
    {
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get token sizes per file.
     *
     * @return array<string, int> Array of filename => token count
     */
    public function getFileTokenSizes(): array
    {
        return $this->fileTokenSizes;
    }

    /**
     * Calculate the total token size across all files.
     */
    public function getTotalTokenSize(): int
    {
        return array_sum($this->fileTokenSizes);
    }

    /**
     * Get the largest file by token count.
     *
     * @return array{filename: string, tokens: int}|null
     */
    public function getLargestFileByTokens(): ?array
    {
        if (count($this->fileTokenSizes) === 0) {
            return null;
        }

        $maxTokens = max($this->fileTokenSizes);
        $filename = array_search($maxTokens, $this->fileTokenSizes, true);

        return [
            'filename' => $filename,
            'tokens' => $maxTokens
        ];
    }

    /**
     * Get files sorted by token count (descending).
     *
     * @return array<string, int>
     */
    public function getFilesSortedByTokens(): array
    {
        $sorted = $this->fileTokenSizes;
        arsort($sorted);
        return $sorted;
    }

    /**
     * Get the number of files processed.
     */
    public function getFileCount(): int
    {
        return count($this->fileTokenSizes);
    }
}
