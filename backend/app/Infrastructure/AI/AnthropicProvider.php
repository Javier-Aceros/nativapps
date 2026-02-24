<?php

namespace App\Infrastructure\AI;

use App\Contracts\AiProvider;
use App\Domain\ValueObjects\Summary;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls the Anthropic Messages API to generate executive summaries.
 * Requires ANTHROPIC_API_KEY in .env.
 */
class AnthropicProvider implements AiProvider
{
    private const BASE_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-haiku-4-5-20251001',
        private readonly string $apiVersion = '2023-06-01',
    ) {}

    public function summarize(string $content): Summary
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
            ])
            ->post(self::BASE_URL, [
                'model' => $this->model,
                'max_tokens' => 60,
                'system' => AiProvider::SYSTEM_PROMPT,
                'messages' => [
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Anthropic API returned HTTP {$response->status()}: {$response->body()}"
            );
        }

        $text = $response->json('content.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Anthropic returned an empty or invalid response.');
        }

        $text = trim($text);

        if (mb_strlen($text) > Summary::MAX_LENGTH) {
            throw new RuntimeException(
                sprintf('Anthropic summary exceeds %d characters (%d). Review the prompt.', Summary::MAX_LENGTH, mb_strlen($text))
            );
        }

        return Summary::fromString($text);
    }
}
