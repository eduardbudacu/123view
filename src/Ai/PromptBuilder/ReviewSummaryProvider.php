<?php

declare(strict_types=1);

namespace DR\Review\Ai\PromptBuilder;

use DR\Review\Ai\Agent\ReviewSummary\ReviewSummaryAgent;
use DR\Review\Entity\Review\CodeReview;
use DR\Review\Service\CodeReview\DiffOutputService;

readonly class ReviewSummaryProvider
{
    public function __construct(
        private DiffOutputService $diffOutputService,
        private ReviewSummaryAgent $summaryAgent
    ) {
    }

    public function getSummaryFromReview(CodeReview $review): string
    {
        $diffOutput = $this->diffOutputService->getDiffOutputFromReview($review);

        return $this->summaryAgent->generateSummary($diffOutput);
    }
}
