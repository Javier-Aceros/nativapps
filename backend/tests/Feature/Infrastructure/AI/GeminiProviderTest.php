<?php

namespace Tests\Feature\Infrastructure\AI;

use App\Contracts\AiProvider;
use App\Domain\ValueObjects\Summary;
use App\Infrastructure\AI\GeminiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    private const API_KEY = 'test-gemini-key';

    private const MODEL = 'gemini-1.5-flash';

    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/*';

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function provider(): GeminiProvider
    {
        return new GeminiProvider(self::API_KEY, self::MODEL);
    }

    private function fakeSuccess(string $text): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => $text]],
                    ],
                ]],
            ], 200),
        ]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_returns_summary_from_successful_response(): void
    {
        $this->fakeSuccess('Nuevo acuerdo climático global firmado');

        $summary = $this->provider()->summarize('Contenido largo de prueba sobre clima.');

        $this->assertInstanceOf(Summary::class, $summary);
        $this->assertSame('Nuevo acuerdo climático global firmado', $summary->value());
    }

    public function test_trims_whitespace_from_api_response(): void
    {
        $this->fakeSuccess("  Resumen con espacios extra  \n");

        $summary = $this->provider()->summarize('Contenido de prueba');

        $this->assertSame('Resumen con espacios extra', $summary->value());
    }

    public function test_throws_when_response_exceeds_100_chars(): void
    {
        $this->fakeSuccess(str_repeat('A', 150));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/exceeds \d+ characters/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_non_2xx_response(): void
    {
        Http::fake([self::GEMINI_URL => Http::response(['error' => ['message' => 'API key invalid']], 400)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Gemini API returned HTTP 400/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_server_error(): void
    {
        Http::fake([self::GEMINI_URL => Http::response('Internal Server Error', 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Gemini API returned HTTP 500/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_empty_text_in_response(): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '']]],
                ]],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty or invalid/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_whitespace_only_response(): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '   ']]],
                ]],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty or invalid/');

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_throws_on_missing_candidates(): void
    {
        Http::fake([self::GEMINI_URL => Http::response(['candidates' => []], 200)]);

        $this->expectException(RuntimeException::class);

        $this->provider()->summarize('Contenido de prueba');
    }

    public function test_includes_api_key_in_request_url(): void
    {
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize('Contenido de prueba');

        Http::assertSent(fn ($req) => str_contains($req->url(), self::API_KEY));
    }

    public function test_includes_model_name_in_request_url(): void
    {
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize('Contenido de prueba');

        Http::assertSent(fn ($req) => str_contains($req->url(), self::MODEL));
    }

    public function test_sends_user_content_in_request_body(): void
    {
        $content = 'Contenido original del usuario';
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize($content);

        Http::assertSent(function ($req) use ($content) {
            $body = $req->data();

            return isset($body['contents'][0]['parts'][0]['text'])
                && $body['contents'][0]['parts'][0]['text'] === $content;
        });
    }

    public function test_sends_system_instruction_in_request_body(): void
    {
        $this->fakeSuccess('Resumen ok');

        $this->provider()->summarize('Contenido');

        Http::assertSent(function ($req) {
            $body = $req->data();

            return ($body['systemInstruction']['parts'][0]['text'] ?? null) === AiProvider::SYSTEM_PROMPT;
        });
    }

    public function test_valid_summary_within_limit_is_accepted(): void
    {
        $this->fakeSuccess('Resumen breve que cumple la regla');

        $summary = $this->provider()->summarize('Contenido de prueba');

        $this->assertLessThanOrEqual(Summary::MAX_LENGTH, mb_strlen($summary->value()));
    }

    public function test_skips_thought_parts_and_returns_response_text(): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [
                            ['thought' => true, 'text' => 'Pensamiento interno del modelo'],
                            ['text' => 'Vacas: rumiantes domésticos clave en leche, carne y fertilización del suelo'],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $summary = $this->provider()->summarize('Contenido sobre vacas');

        $this->assertSame(
            'Vacas: rumiantes domésticos clave en leche, carne y fertilización del suelo',
            $summary->value()
        );
    }

    public function test_includes_configured_api_version_in_request_url(): void
    {
        $this->fakeSuccess('Resumen ok');

        $provider = new GeminiProvider(self::API_KEY, self::MODEL, 'v1');
        $provider->summarize('Contenido de prueba');

        Http::assertSent(fn ($req) => str_contains($req->url(), '/v1/'));
    }
}
