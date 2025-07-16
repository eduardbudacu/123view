<?php

declare(strict_types=1);

namespace DR\Review\Ai\Summary;

readonly class AiSummaryResponse
{
    public function __construct(public string $summary, public array $context = [])
    {
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
