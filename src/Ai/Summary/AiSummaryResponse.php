<?php

declare(strict_types=1);

namespace DR\Review\Ai\Summary;

readonly class AiSummaryResponse
{
    /**
     * @param string $summary The AI-generated summary
     * @param array<int, array{role: string, content: string}> $context The context used for the AI request
     * @param TokenAnalysis $tokenAnalysis Token analysis data
     */
    public function __construct(
        public string $summary,
        public array $context = [],
        public TokenAnalysis $tokenAnalysis = new TokenAnalysis()
    ) {
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the token analysis instance.
     */
    public function getTokenAnalysis(): TokenAnalysis
    {
        return $this->tokenAnalysis;
    }
}
