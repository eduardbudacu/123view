<?php

declare(strict_types=1);

namespace DR\Review\Ai\Agent\ReviewSummary;

use DR\Review\Ai\Summary\AiSummaryResponse;
use DR\Utils\Assert;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ReviewSummaryAgent
{
    private string $systemInstructions;

    public function __construct(private readonly Client $openAIClient, private ParameterBagInterface $params)
    {
        $this->setSystemInstructions();
    }

    public function generateSummary(string $diff): AiSummaryResponse
    {
        try {
            $context        = [
                ['role' => 'system', 'content' => $this->systemInstructions],
                ['role' => 'user', 'content' => $diff],
            ];
            $openAIResponse = $this->openAIClient->chat()->create([
                'model'    => 'gpt-3.5-turbo',
                'messages' => $context,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to generate summary: ' . $e->getMessage() . ' Context' . print_r($context, true), 0, $e);
        }

        return new AiSummaryResponse(Assert::notNull($openAIResponse->choices[0]->message->content), $context);
    }

    private function setSystemInstructions(): void
    {
        $filePath                 = $this->params->get('kernel.project_dir') . '/prompts/review-summary.md';
        $fileContent              = file_get_contents($filePath);
        $this->systemInstructions = $fileContent !== false ? $fileContent : '';
    }
}
