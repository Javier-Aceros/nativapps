<?php

namespace Tests\Feature\Infrastructure\AI;

use App\Infrastructure\AI\AiProviderFactory;
use App\Infrastructure\AI\AnthropicProvider;
use App\Infrastructure\AI\GeminiProvider;
use App\Infrastructure\AI\OpenAiProvider;
use InvalidArgumentException;
use OpenAI\Client;
use OpenAI\Testing\ClientFake;
use Tests\TestCase;

class AiProviderFactoryTest extends TestCase
{
    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_returns_gemini_provider_for_gemini(): void
    {
        config(['services.ai.gemini.api_key' => 'fake-key']);

        $provider = AiProviderFactory::make('gemini');

        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }

    public function test_returns_anthropic_provider_for_anthropic(): void
    {
        config(['services.ai.anthropic.api_key' => 'fake-key']);

        $provider = AiProviderFactory::make('anthropic');

        $this->assertInstanceOf(AnthropicProvider::class, $provider);
    }

    public function test_returns_openai_provider_for_openai(): void
    {
        // ClientFake implements ClientContract — safe to bind as the Client::class entry
        $this->app->instance(Client::class, new ClientFake([]));

        $provider = AiProviderFactory::make('openai');

        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function test_throws_for_unknown_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported AI provider.*whatsapp/');

        AiProviderFactory::make('whatsapp');
    }

    public function test_throws_for_empty_provider_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AiProviderFactory::make('');
    }

    public function test_gemini_provider_uses_configured_model(): void
    {
        config([
            'services.ai.gemini.api_key' => 'fake-key',
            'services.ai.gemini.model' => 'gemini-pro',
        ]);

        $provider = AiProviderFactory::make('gemini');

        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }

    public function test_ai_provider_contract_is_bound_in_container(): void
    {
        config([
            'services.ai.provider' => 'gemini',
            'services.ai.gemini.api_key' => 'fake-key',
        ]);

        $provider = $this->app->make(\App\Contracts\AiProvider::class);

        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }
}
