<?php

declare(strict_types=1);

namespace DR\Review\Controller\App\Review;

use DR\Review\Ai\PromptBuilder\ReviewSummaryProvider;
use DR\Review\Ai\Summary\AiSummaryResponse;
use DR\Review\Controller\AbstractController;
use DR\Review\Entity\Review\CodeReview;
use DR\Review\Model\Page\Breadcrumb;
use DR\Review\Request\Review\ReviewRequest;
use DR\Review\Security\Role\Roles;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

class ReviewSummaryController extends AbstractController
{
    public function __construct(private readonly ReviewSummaryProvider $reviewSummaryProvider)
    {
    }

    /**
     * @return array<string, string|object|Breadcrumb[]>
     * @throws Throwable
     */
    #[Route('app/{repositoryName<[\w-]+>}/review-summary-debug/cr-{reviewId<\d+>}', name: self::class . '_debug', methods: 'GET')]
    #[Template('app/review/review.html.twig')]
    #[IsGranted(Roles::ROLE_USER)]
    public function debug(
        ReviewRequest $request,
        #[MapEntity(expr: 'repository.findByUrl(repositoryName, reviewId)')] CodeReview $review
    ): array {
        $result = $this->reviewSummaryProvider->getDiffAnalysisFromReview($review);
        $this->dumpAnalysis($result);
    }

    /**
     * @return array<string, string|object|Breadcrumb[]>
     * @throws Throwable
     */
    #[Route('app/{repositoryName<[\w-]+>}/review-summary/cr-{reviewId<\d+>}', name: self::class, methods: 'GET')]
    #[Template('app/review/review.html.twig')]
    #[IsGranted(Roles::ROLE_USER)]
    public function __invoke(ReviewRequest $request, #[MapEntity(expr: 'repository.findByUrl(repositoryName, reviewId)')] CodeReview $review): array
    {
        $result = $this->reviewSummaryProvider->getSummaryFromReview($review);
        $this->dumpAnalysis($result);
    }

    /**
     * Dump analysis data from AI summary results with comprehensive token analysis.
     */
    private function dumpAnalysis(AiSummaryResponse $aiResult): never
    {
        $debugData = [
            'summary'         => $aiResult->getSummary(),
            'context'         => $aiResult->getContext(),
            'token_analysis'  => [
                'total_tokens'           => $aiResult->getTotalTokenSize(),
                'file_count'             => $aiResult->getFileCount(),
                'largest_file'           => $aiResult->getLargestFileByTokens(),
                'files_sorted_by_tokens' => $aiResult->getFilesSortedByTokens(),
                'file_token_sizes'       => $aiResult->getFileTokenSizes()
            ],
            'original_result' => $aiResult
        ];

        dd($debugData);
    }
}
