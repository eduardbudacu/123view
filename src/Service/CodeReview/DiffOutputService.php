<?php
declare(strict_types=1);

namespace DR\Review\Service\CodeReview;

use DR\Review\Entity\Git\Diff\DiffComparePolicy;
use DR\Review\Entity\Git\Diff\DiffFile;
use DR\Review\Entity\Review\CodeReview;
use DR\Review\Service\Git\Review\FileDiffOptions;
use DR\Review\Service\Git\Review\ReviewDiffService\ReviewDiffServiceInterface;
use DR\Utils\Assert;

class DiffOutputService
{
    public function __construct(
        private readonly CodeReviewRevisionService $revisionService,
        private readonly ReviewDiffServiceInterface $diffService,
    ) {
    }

    public function getDiffOutputFromReview(CodeReview $review): string
    {
        $revisions  = $this->revisionService->getRevisions($review);
        $repository = Assert::notNull($review->getRepository());

        $reviewType  = $review->getType();
        $diffOptions = new FileDiffOptions(FileDiffOptions::DEFAULT_LINE_DIFF, DiffComparePolicy::ALL, $reviewType);
        $files       = $this->diffService->getDiffForRevisions($repository, $revisions, $diffOptions);
        $output      = implode("\n", array_map(static fn(DiffFile $file) => $file->rawContent, $files));

        return $output;
    }
}
