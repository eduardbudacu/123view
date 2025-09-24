<?php
declare(strict_types=1);

namespace DR\Review\Ai\Targetproccess\Entity;

class UserTask
{
    public function __construct(
        public readonly int $Id,
        public readonly string $Name,
        public readonly ?string $Description,
        public ?UserStory $UserStory,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $userStory = null;
        $story     = $data['UserStory'];
        if (isset($story) && is_array($story)) {
            $userStoryData = [
                'Id'          => $story['Id'] ?? null,
                'Name'        => $story['Name'] ?? null,
                'Description' => null,
            ];
            $userStory     = UserStory::fromArray($userStoryData);
        }

        return new self(
            $data['Id'],
            $data['Name'],
            $data['Description'] ?? null,
            $userStory
        );
    }
}
