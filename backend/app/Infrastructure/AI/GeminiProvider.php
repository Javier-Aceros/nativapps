<?php

namespace App\Infrastructure\AI;

use App\Contracts\AiProvider;
use App\Domain\ValueObjects\Summary;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls the Gemini REST API to generate executive summaries.
 * Requires GEMINI_API_KEY in .env.
 */
class GeminiProvider implements AiProvider
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/%s/models';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-1.5-flash',
        private readonly string $apiVersion = 'v1beta',
    ) {}

    public function summarize(string $content): Summary
    {
        $url = sprintf(self::BASE_URL, $this->apiVersion)."/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(30)->post($url, [
            'system_instruction' => [
                'parts' => [['text' => AiProvider::SYSTEM_PROMPT]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $content]],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 60,
                'temperature' => 0.2,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Gemini API returned HTTP {$response->status()}: {$response->body()}"
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini returned an empty or invalid response.');
        }

        $text = trim($text);

        if (mb_strlen($text) > Summary::MAX_LENGTH) {
            throw new RuntimeException(
                sprintf('Gemini summary exceeds %d characters (%d). Review the prompt.', Summary::MAX_LENGTH, mb_strlen($text))
            );
        }

        return Summary::fromString($text);
    }
}
