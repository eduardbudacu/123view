<?php

declare(strict_types=1);

namespace DR\Review\Controller\App\Review;

use DR\Review\Ai\PromptBuilder\ReviewSummaryProvider;
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
    #[Route('app/{repositoryName<[\w-]+>}/review-summary/cr-{reviewId<\d+>}', name: self::class, methods: 'GET')]
    #[Template('app/review/review.html.twig')]
    #[IsGranted(Roles::ROLE_USER)]
    public function __invoke(ReviewRequest $request, #[MapEntity(expr: 'repository.findByUrl(repositoryName, reviewId)')] CodeReview $review): array
    {
        $result = $this->reviewSummaryProvider->getSummaryFromReview($review);
        dd($result);
    }

    /**
     * @return array<string, string|object|Breadcrumb[]>
     * @throws Throwable
     */
    #[Route('app/{repositoryName<[\w-]+>}/review-summary-debug/cr-{reviewId<\d+>}', name: self::class . '_debug', methods: 'GET')]
    #[Template('app/review/review.html.twig')]
    #[IsGranted(Roles::ROLE_USER)]
    public function __invokeDebug(ReviewRequest $request, #[MapEntity(expr: 'repository.findByUrl(repositoryName, reviewId)')] CodeReview $review): array
    {
        $result = $this->reviewSummaryProvider->getDiffOutputFromReview($review);
        dd($result);
    }
}
