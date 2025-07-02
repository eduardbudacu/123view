<?php

declare(strict_types=1);

namespace DR\Review\Controller\App\Review;

use DR\Review\Controller\AbstractController;
use DR\Review\Entity\Git\Diff\DiffComparePolicy;
use DR\Review\Entity\Git\Diff\DiffFile;
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

        $reviewType = $review->getType();
        $diffOptions = new FileDiffOptions(FileDiffOptions::DEFAULT_LINE_DIFF, DiffComparePolicy::ALL, $reviewType);
        $files = $this->diffService->getDiffForRevisions($repository, $revisions, $diffOptions);
        $output = implode("\n", array_map(fn (DiffFile $file) => $file->rawContent, $files));

        $filePath = $this->getParameter('kernel.project_dir') . '/prompts/review-summary.md';

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $fileContent = file_get_contents($filePath);

        $result = $this->openAIClient->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $fileContent],
                ['role' => 'user', 'content' => 'Summarize the following diff:' . $output],
            ],
        ]);
        dd($result->choices[0]->message->content);
    }
}
