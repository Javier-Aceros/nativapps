<?php

namespace App\Infrastructure\AI;

use App\Contracts\AiProvider;
use InvalidArgumentException;
use OpenAI\Client;

/**
 * Resolves the correct AiProvider implementation based on AI_PROVIDER env.
 *
 * Supported values: openai | gemini | anthropic
 *
 * To add a new provider: create a class implementing AiProvider, add a case
 * to the match expression below, and set AI_PROVIDER in .env — nothing else changes.
 */
class AiProviderFactory
{
    public static function make(string $provider): AiProvider
    {
        return match ($provider) {
            'openai' => new OpenAiProvider(
                client: app(Client::class),
                model: (string) config('openai.model', 'gpt-4o-mini'),
            ),
            'gemini' => new GeminiProvider(
                apiKey: (string) config('services.ai.gemini.api_key'),
                model: (string) config('services.ai.gemini.model', 'gemini-1.5-flash'),
                apiVersion: (string) config('services.ai.gemini.api_version', 'v1beta'),
            ),
            'anthropic' => new AnthropicProvider(
                apiKey: (string) config('services.ai.anthropic.api_key'),
                model: (string) config('services.ai.anthropic.model', 'claude-haiku-4-5-20251001'),
                apiVersion: (string) config('services.ai.anthropic.api_version', '2023-06-01'),
            ),
            default => throw new InvalidArgumentException(
                "Unsupported AI provider: [{$provider}]. Supported values: openai, gemini, anthropic."
            ),
        };
    }
}
