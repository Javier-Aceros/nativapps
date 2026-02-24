<?php

namespace App\Infrastructure\AI;

use App\Contracts\AiProvider;
use App\Domain\ValueObjects\Summary;
use OpenAI\Contracts\ClientContract;
use RuntimeException;

/**
 * Calls the OpenAI Chat Completions API to generate executive summaries.
 * Requires OPENAI_API_KEY in .env (handled by openai-php/laravel).
 *
 * Accepts ClientContract so tests can inject ClientFake without reflection hacks.
 */
class OpenAiProvider implements AiProvider
{
    public function __construct(
        private readonly ClientContract $client,
        private readonly string $model = 'gpt-4o-mini',
    ) {}

    public function summarize(string $content): Summary
    {
        try {
            $result = $this->client->chat()->create([
                'model' => $this->model,
                'max_tokens' => 60,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => AiProvider::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException("OpenAI API error: {$e->getMessage()}", previous: $e);
        }

        $text = $result->choices[0]->message->content ?? '';

        if (trim($text) === '') {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        $text = trim($text);

        if (mb_strlen($text) > Summary::MAX_LENGTH) {
            throw new RuntimeException(
                sprintf('OpenAI summary exceeds %d characters (%d). Review the prompt.', Summary::MAX_LENGTH, mb_strlen($text))
            );
        }

        return Summary::fromString($text);
    }
}
