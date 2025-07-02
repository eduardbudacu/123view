<?php

declare(strict_types=1);

namespace DR\Review\Ai\Agent\ReviewSummary;

use DR\Review\Ai\Summary\AiSummaryResponse;
use DR\Utils\Assert;
use OpenAI\Client;

class ReviewSummaryAgent
{
    private string $systemInstructions;

    public function __construct(private readonly Client $openAIClient,)
    {
        $this->setSystemInstructions();
    }

    public function generateSummary(string $diff): string
    {
        $openAIResponse = $this->openAIClient->chat()->create([
            'model'    => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $this->systemInstructions],
                ['role' => 'user', 'content' => $diff],
            ],
        ]);

        $response = new AiSummaryResponse(Assert::notNull($openAIResponse->choices[0]->message->content));

        return $response->getSummary();
    }

    private function setSystemInstructions(): void
    {
        $filePath                 = $this->getParameter('kernel.project_dir') . '/prompts/review-summary.md';
        $fileContent              = file_get_contents($filePath);
        $this->systemInstructions = $fileContent;
    }
}
