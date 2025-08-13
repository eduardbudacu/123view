<?php

declare(strict_types=1);

namespace DR\Review\Ai\PromptBuilder;

use DR\Review\Ai\Agent\ReviewSummary\ReviewSummaryAgent;
use DR\Review\Ai\Summary\AiSummaryResponse;
use DR\Review\Ai\Summary\TokenAnalysis;
use DR\Review\Entity\Review\CodeReview;
use DR\Review\Service\CodeReview\DiffOutputService;

readonly class ReviewSummaryProvider
{
    public function __construct(private DiffOutputService $diffOutputService, private ReviewSummaryAgent $summaryAgent)
    {
    }

    public function getSummaryFromReview(CodeReview $review): AiSummaryResponse
    {
        $diffFiles = $this->diffOutputService->getDiffFilesFromReview($review);

        // Filter files based on token limits and get token sizes
        $validationResult = $this->summaryAgent->validateTokenCountForFiles($diffFiles);
        $filteredFiles    = $validationResult['files'];
        $fileTokenSizes   = $validationResult['tokenSizes'];
        $excludedFiles    = $validationResult['filteredFiles'];

        // Sort excluded files by tokens in descending order
        usort($excludedFiles, fn($a, $b) => $b['tokens'] <=> $a['tokens']);

        // Prepare diff output from filtered files
        $diffOutput = $this->summaryAgent->prepareDiffOutput($filteredFiles);

        $aiResponse = $this->summaryAgent->generateSummary($diffOutput);

        // Create TokenAnalysis object
        $tokenAnalysis = new TokenAnalysis($fileTokenSizes, $excludedFiles);

        // Return response with token analysis
        return new AiSummaryResponse(
            $aiResponse->getSummary(),
            $aiResponse->getContext(),
            $tokenAnalysis
        );
    }

    /**
     * Get diff analysis as AiSummaryResponse with token information but without AI processing.
     */
    public function getDiffAnalysisFromReview(CodeReview $review): AiSummaryResponse
    {
        $diffFiles = $this->diffOutputService->getDiffFilesFromReview($review);

        // Filter files based on token limits and get token sizes (for analysis purposes)
        $validationResult = $this->summaryAgent->validateTokenCountForFiles($diffFiles);
        $filteredFiles    = $validationResult['files'];
        $fileTokenSizes   = $validationResult['tokenSizes'];
        $excludedFiles    = $validationResult['filteredFiles'];

        // Sort excluded files by tokens in descending order
        usort($excludedFiles, fn($a, $b) => $b['tokens'] <=> $a['tokens']);

        // Prepare the context that would be sent to AI (without actually sending it)
        $diffOutput = $this->summaryAgent->prepareDiffOutput($filteredFiles);
        $context    = [
            ['role' => 'system', 'content' => $this->summaryAgent->getSystemInstructions()],
            ['role' => 'user', 'content' => $diffOutput],
        ];

        // Create TokenAnalysis object
        $tokenAnalysis = new TokenAnalysis($fileTokenSizes, $excludedFiles);

        // Return AiSummaryResponse with context, token data, and filtered files
        return new AiSummaryResponse(
            summary: '',  // No AI summary generated
            context: $context,  // Context that would be sent to AI
            tokenAnalysis: $tokenAnalysis
        );
    }
}
