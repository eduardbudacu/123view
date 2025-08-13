<?php

declare(strict_types=1);

namespace DR\Review\Ai\PromptBuilder;

use DR\Review\Ai\Agent\ReviewSummary\ReviewSummaryAgent;
use DR\Review\Ai\Summary\AiSummaryResponse;
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

        // Prepare diff output from filtered files
        $diffOutput = $this->summaryAgent->prepareDiffOutput($filteredFiles);

        $aiResponse = $this->summaryAgent->generateSummary($diffOutput);

        // Return response with token size information
        return new AiSummaryResponse(
            $aiResponse->getSummary(),
            $aiResponse->getContext(),
            $fileTokenSizes
        );
    }

    /**
     * Get diff analysis as AiSummaryResponse with token information but without AI processing.
     */
    public function getDiffAnalysisFromReview(CodeReview $review): AiSummaryResponse
    {
        $diffFiles = $this->diffOutputService->getDiffFilesFromReview($review);

        // Get token sizes for each file without filtering (for analysis purposes)
        $fileTokenSizes = [];
        foreach ($diffFiles as $file) {
            $filename                  = $file->getPathname();
            $fileTokens                = $this->summaryAgent->estimateTokenCountForContent($file->rawContent ?? '');
            $fileTokenSizes[$filename] = $fileTokens;
        }

        // Prepare the context that would be sent to AI (without actually sending it)
        $diffOutput = $this->summaryAgent->prepareDiffOutput($diffFiles);
        $context    = [
            ['role' => 'system', 'content' => $this->summaryAgent->getSystemInstructions()],
            ['role' => 'user', 'content' => $diffOutput],
        ];

        // Return AiSummaryResponse with context and token data
        return new AiSummaryResponse(
            summary       : '',  // No AI summary generated
            context       : $context,  // Context that would be sent to AI
            fileTokenSizes: $fileTokenSizes
        );
    }
}
