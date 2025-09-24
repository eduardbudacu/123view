<?php
declare(strict_types=1);

namespace DR\Review\Ai\Targetproccess\Entity;

class UserStory
{
    public function __construct(
        public readonly int $Id,
        public readonly string $Name,
        public readonly ?string $Description
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['Id'],
            $data['Name'],
            $data['Description'] ?? null
        );
    }
}
