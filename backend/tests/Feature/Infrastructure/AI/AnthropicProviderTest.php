<?php

namespace Tests\Feature\Infrastructure\AI;

use App\Domain\ValueObjects\Summary;
use App\Infrastructure\AI\AnthropicProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AnthropicProviderTest extends TestCase
{
    private const API_KEY = 'test-anthropic-key';

    private const MODEL = 'claude-haiku-4-5-20251001';

    private const ANTHROPIC_URL = 'https://api.anthropic.com/*';

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function provider(): AnthropicProvider
    {
        return new AnthropicProvider(self::API_KEY, self::MODEL);
    }

    private function fakeSuccess(string $text): void
    {
        Http::fake([
            self::ANTHROPIC_URL => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => $text],
                ],
            ], 200),
        ]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_returns_summary_from_successful_response(): void
    {
        $this->fakeSuccess('Cumbre climática genera acuerdo histórico');

        $summary = $this->provider()->summarize('Contenido largo sobre el clima global.');

        $this->assertInstanceOf(Summary::class, $summary);
        $this->assertSame('Cumbre climática genera acuerdo histórico', $summary->value());
    }

    public function test_trims_whitespace_from_api_response(): void
    {
        $this->fakeSuccess("  Resumen con espacios  \n");

        $summary = $this->provider()->summarize('Contenido de prueba');

        $this->assertSame('Resumen con espacios', $summary->value());
    }

    public function test_throws_when_response_exceeds_100_chars(): void
    {
        $this->fakeSuccess(str_repeat('B', 150));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/exceeds \d+ characters/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_non_2xx_response(): void
    {
        Http::fake([self::ANTHROPIC_URL => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Anthropic API returned HTTP 401/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_server_error(): void
    {
        Http::fake([self::ANTHROPIC_URL => Http::response('Internal Server Error', 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Anthropic API returned HTTP 500/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_empty_text_in_response(): void
    {
        Http::fake([
            self::ANTHROPIC_URL => Http::response([
                'content' => [['type' => 'text', 'text' => '']],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty or invalid/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_missing_content_in_response(): void
    {
        Http::fake([self::ANTHROPIC_URL => Http::response(['content' => []], 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty or invalid/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_sends_api_key_header(): void
    {
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize('Contenido de prueba');

        Http::assertSent(fn ($req) => $req->hasHeader('x-api-key', self::API_KEY));
    }

    public function test_sends_anthropic_version_header(): void
    {
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize('Contenido de prueba');

        Http::assertSent(fn ($req) => $req->hasHeader('anthropic-version', '2023-06-01'));
    }

    public function test_sends_user_content_in_request_body(): void
    {
        $content = 'Texto original del usuario para resumir';
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize($content);

        Http::assertSent(function ($req) use ($content) {
            $body = $req->data();

            return isset($body['messages'][0]['content'])
                && $body['messages'][0]['content'] === $content
                && $body['messages'][0]['role'] === 'user';
        });
    }

    public function test_sends_system_prompt_in_request_body(): void
    {
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize('Contenido');

        Http::assertSent(function ($req) {
            $body = $req->data();

            return isset($body['system'])
                && str_contains($body['system'], '100 caracteres');
        });
    }

    public function test_sends_correct_model_in_request_body(): void
    {
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize('Contenido');

        Http::assertSent(function ($req) {
            return $req->data()['model'] === self::MODEL;
        });
    }

    public function test_valid_summary_within_limit_is_accepted(): void
    {
        $this->fakeSuccess('Resumen breve');

        $summary = $this->provider()->summarize('Contenido de prueba');

        $this->assertLessThanOrEqual(Summary::MAX_LENGTH, mb_strlen($summary->value()));
    }

    public function test_sends_configured_api_version_header(): void
    {
        $this->fakeSuccess('Resumen ok');

        $provider = new AnthropicProvider(self::API_KEY, self::MODEL, '2024-01-01');
        $provider->summarize('Contenido de prueba');

        Http::assertSent(fn ($req) => $req->hasHeader('anthropic-version', '2024-01-01'));
    }
}
