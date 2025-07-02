<?php
declare(strict_types=1);

namespace DR\Review\Controller\App\Review;

use DR\Review\Controller\AbstractController;
use DR\Review\Entity\Git\Diff\DiffComparePolicy;
use DR\Review\Entity\Review\CodeReview;
use DR\Review\Model\Page\Breadcrumb;
use DR\Review\Request\Review\ReviewRequest;
use DR\Review\Security\Role\Roles;
use DR\Review\Service\CodeReview\CodeReviewRevisionService;
use DR\Review\Service\Git\Review\FileDiffOptions;
use DR\Review\Service\Git\Review\ReviewDiffService\ReviewDiffServiceInterface;
use DR\Review\Service\Git\Review\Strategy\BasicCherryPickStrategy;
use DR\Utils\Assert;
use OpenAI\Client;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

class ReviewSummaryController extends AbstractController
{
    public function __construct(
        private readonly CodeReviewRevisionService $revisionService,
        private readonly ReviewDiffServiceInterface $diffService,
        private readonly BasicCherryPickStrategy $basicCherryPickStrategy,
        private readonly Client $openAIClient,
    ) {
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
        $revisions = $this->revisionService->getRevisions($review);
        $repository = Assert::notNull($review->getRepository());
        $result = $this->openAIClient->completions()->create([
            'model' => 'gpt-3.5-turbo-instruct',
            'prompt' => 'PHP is',
        ]);
        dd($result->choices[0]->text);
        $reviewType = $review->getType();
        $diffOptions = new FileDiffOptions(FileDiffOptions::DEFAULT_LINE_DIFF, DiffComparePolicy::ALL, $reviewType);
        $files = $this->diffService->getDiffForRevisions($repository, $revisions, $diffOptions);
        $output = $this->basicCherryPickStrategy->getDiffOutput($repository, $revisions, $diffOptions);
        dd($review, $revisions, $files, $output);
    }
}
