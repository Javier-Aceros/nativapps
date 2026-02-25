<?php

namespace Tests\Feature\Infrastructure\AI;

use App\Contracts\AiProvider;
use App\Domain\ValueObjects\Summary;
use App\Infrastructure\AI\OpenAiProvider;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;
use RuntimeException;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    private const MODEL = 'gpt-4o-mini';

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function fakeClientWithContent(string $content): ClientFake
    {
        return new ClientFake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'index' => 0,
                        'finish_reason' => 'stop',
                        'logprobs' => null,
                        'message' => [
                            'role' => 'assistant',
                            'content' => $content,
                            'function_call' => null,
                            'tool_calls' => [],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    private function provider(ClientFake $client): OpenAiProvider
    {
        return new OpenAiProvider($client, self::MODEL);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_returns_summary_from_successful_response(): void
    {
        $client = $this->fakeClientWithContent('Empresa lanza producto revolucionario');
        $summary = $this->provider($client)->summarize('Contenido largo del artículo.');

        $this->assertInstanceOf(Summary::class, $summary);
        $this->assertSame('Empresa lanza producto revolucionario', $summary->value());
    }

    public function test_trims_whitespace_from_api_response(): void
    {
        $client = $this->fakeClientWithContent("  Resumen con espacios  \n");
        $summary = $this->provider($client)->summarize('Contenido de prueba');

        $this->assertSame('Resumen con espacios', $summary->value());
    }

    public function test_throws_when_response_exceeds_100_chars(): void
    {
        $client = $this->fakeClientWithContent(str_repeat('C', 150));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/exceeds \d+ characters/');

        $this->provider($client)->summarize('Contenido de prueba');
    }

    public function test_throws_when_client_raises_exception(): void
    {
        $client = new ClientFake([new \Exception('Connection refused')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/OpenAI API error/');

        $this->provider($client)->summarize('Contenido de prueba');
    }

    public function test_throws_on_empty_content_in_response(): void
    {
        $client = $this->fakeClientWithContent('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty response/');

        $this->provider($client)->summarize('Contenido de prueba');
    }

    public function test_throws_on_whitespace_only_content(): void
    {
        $client = $this->fakeClientWithContent('   ');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty response/');

        $this->provider($client)->summarize('Contenido de prueba');
    }

    public function test_sends_correct_model_in_request(): void
    {
        $client = $this->fakeClientWithContent('Resumen ok');
        $this->provider($client)->summarize('Contenido');

        $client->chat()->assertSent(function (string $method, array $params): bool {
            return $method === 'create' && $params['model'] === self::MODEL;
        });
    }

    public function test_sends_system_prompt_in_request(): void
    {
        $client = $this->fakeClientWithContent('Resumen ok');
        $this->provider($client)->summarize('Contenido');

        $client->chat()->assertSent(function (string $method, array $params): bool {
            $systemMsg = collect($params['messages'])->firstWhere('role', 'system');

            return $method === 'create'
                && ($systemMsg['content'] ?? null) === AiProvider::SYSTEM_PROMPT;
        });
    }

    public function test_sends_user_content_in_request(): void
    {
        $userContent = 'Texto del usuario para resumir';
        $client = $this->fakeClientWithContent('Resumen ok');
        $this->provider($client)->summarize($userContent);

        $client->chat()->assertSent(function (string $method, array $params) use ($userContent): bool {
            $userMsg = collect($params['messages'])->firstWhere('role', 'user');

            return $method === 'create'
                && $userMsg !== null
                && $userMsg['content'] === $userContent;
        });
    }

    public function test_valid_summary_within_limit_is_accepted(): void
    {
        $client = $this->fakeClientWithContent('Resumen breve y válido');
        $summary = $this->provider($client)->summarize('Contenido de prueba');

        $this->assertLessThanOrEqual(Summary::MAX_LENGTH, mb_strlen($summary->value()));
    }
}
