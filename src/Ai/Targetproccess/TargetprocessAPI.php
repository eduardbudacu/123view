<?php
declare(strict_types=1);

namespace DR\Review\Ai\Targetproccess;

use DR\Review\Ai\Targetproccess\Entity\UserStory;
use DR\Review\Ai\Targetproccess\Entity\UserTask;
use DR\Review\Entity\Review\CodeReview;
use DR\Review\Service\CodeReview\CodeReviewRevisionService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TargetprocessAPI
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $accessToken,
        private readonly CodeReviewRevisionService $revisionService
    ) {
    }

    private function requestEntityById(string $entityType): array
    {
        $response = $this->httpClient->request('GET', $this->apiUrl . $entityType . '?access_token=' . $this->accessToken, [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return $response->toArray();
    }

    public function getUserStoryById(int $id): UserStory
    {
        $data = $this->requestEntityById('UserStories/' . $id);

        return UserStory::fromArray($data);
    }

    public function getTaskById(int $id): UserTask
    {
        $data = $this->requestEntityById('Tasks/' . $id);

        return UserTask::fromArray($data);
    }

    /**
     * Extract distinct task IDs from revision titles in the format 'T#<number> ' (T# followed by digits and a space).
     * @return int[]
     */
    public function getTargetprocessTaskIdsFromCodeReview(CodeReview $review): array
    {
        $taskIds   = [];
        $revisions = $this->revisionService->getRevisions($review);

        foreach ($revisions as $revision) {
            $title = $revision->getTitle();
            if ($title === null) {
                continue;
            }
            if (!preg_match_all('/T#(\d+) /', $title, $matches)) {
                continue;
            }
            foreach ($matches[1] as $id) {
                $taskIds[] = (int)$id;
            }
        }

        return array_values(array_unique($taskIds));
    }

    public function getTasksAndStoriesFromCodeReview(CodeReview $review): array
    {
        $tasks      = [];
        $stories    = [];
        $storyCache = [];
        $taskIds    = $this->getTargetprocessTaskIdsFromCodeReview($review);
        foreach ($taskIds as $id) {
            $task    = $this->getTaskById($id);
            $tasks[] = $task;
            if ($task->UserStory !== null) {
                $storyId = $task->UserStory->Id;
                if (!isset($storyCache[$storyId])) {
                    $storyCache[$storyId] = $this->getUserStoryById($storyId);
                }
                $stories[]       = $storyCache[$storyId];
                $task->UserStory = $storyCache[$storyId];
            }
        }

        return ['tasks' => $tasks, 'stories' => $stories];
    }
}
