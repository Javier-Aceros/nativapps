<?php

namespace Tests\Feature\Http\Controllers;

use App\Contracts\AiProvider;
use App\Domain\Enums\Channel;
use App\Domain\Enums\MessageStatus;
use App\Domain\ValueObjects\Summary;
use App\Events\MessageProcessed;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── POST /api/messages ───────────────────────────────────────────────────

    public function test_store_creates_message_dispatches_event_and_returns_201(): void
    {
        Event::fake([MessageProcessed::class]);

        $this->mock(AiProvider::class)
            ->shouldReceive('summarize')
            ->once()
            ->with('Este es el contenido completo del mensaje.')
            ->andReturn(Summary::fromString('Resumen ejecutivo breve'));

        $response = $this->postJson('/api/messages', [
            'title' => 'Reunión de lanzamiento',
            'content' => 'Este es el contenido completo del mensaje.',
            'channels' => ['email', 'slack'],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id', 'title', 'summary', 'status',
                'channels', 'delivery_logs',
            ])
            ->assertJsonPath('title', 'Reunión de lanzamiento')
            ->assertJsonPath('summary', 'Resumen ejecutivo breve')
            ->assertJsonPath('status', MessageStatus::Pending->value);

        $this->assertDatabaseHas('messages', [
            'title' => 'Reunión de lanzamiento',
            'summary' => 'Resumen ejecutivo breve',
        ]);

        Event::assertDispatched(MessageProcessed::class, function (MessageProcessed $event) {
            return $event->message->title === 'Reunión de lanzamiento';
        });
    }

    public function test_store_returns_422_problem_detail_when_ai_fails(): void
    {
        $this->mock(AiProvider::class)
            ->shouldReceive('summarize')
            ->once()
            ->andThrow(new \RuntimeException('Gemini API returned HTTP 503: Service Unavailable'));

        $response = $this->postJson('/api/messages', [
            'title' => 'Test de fallo de IA',
            'content' => 'Contenido que la IA no puede procesar.',
            'channels' => ['email'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('title', 'AI Processing Error')
            ->assertJsonPath('status', 422)
            ->assertJsonStructure(['type', 'title', 'status', 'detail']);

        // Nothing must be persisted when AI fails.
        $this->assertDatabaseMissing('messages', ['title' => 'Test de fallo de IA']);
    }

    public function test_store_returns_422_when_required_fields_are_missing(): void
    {
        $response = $this->postJson('/api/messages', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content', 'channels']);
    }

    public function test_store_returns_422_when_content_is_too_short(): void
    {
        $response = $this->postJson('/api/messages', [
            'title' => 'Título válido',
            'content' => 'Corto',
            'channels' => ['email'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_store_returns_422_when_channel_value_is_invalid(): void
    {
        $response = $this->postJson('/api/messages', [
            'title' => 'Título válido',
            'content' => 'Contenido suficientemente largo para pasar validación.',
            'channels' => ['whatsapp'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channels.0']);
    }

    public function test_store_returns_422_when_channels_array_is_empty(): void
    {
        $response = $this->postJson('/api/messages', [
            'title' => 'Título válido',
            'content' => 'Contenido suficientemente largo para pasar validación.',
            'channels' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channels']);
    }

    public function test_store_does_not_dispatch_event_when_ai_fails(): void
    {
        Event::fake([MessageProcessed::class]);

        $this->mock(AiProvider::class)
            ->shouldReceive('summarize')
            ->once()
            ->andThrow(new \RuntimeException('AI error'));

        $this->postJson('/api/messages', [
            'title' => 'No debe disparar evento',
            'content' => 'Contenido suficientemente largo para pasar validación.',
            'channels' => ['email'],
        ]);

        Event::assertNotDispatched(MessageProcessed::class);
    }

    // ─── GET /api/messages ────────────────────────────────────────────────────

    public function test_index_returns_paginated_messages_with_delivery_logs(): void
    {
        Message::factory()->count(3)->create();

        $response = $this->getJson('/api/messages');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'summary', 'status', 'delivery_logs']],
                'total', 'per_page', 'current_page',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_empty_data_when_no_messages_exist(): void
    {
        $response = $this->getJson('/api/messages');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('total', 0);
    }

    public function test_index_returns_messages_ordered_newest_first(): void
    {
        $older = Message::factory()->create(['created_at' => now()->subHour()]);
        $newer = Message::factory()->create(['created_at' => now()]);

        $response = $this->getJson('/api/messages');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertSame($newer->id, $ids->first());
        $this->assertSame($older->id, $ids->last());
    }

    // ─── GET /api/messages/{message} ─────────────────────────────────────────

    public function test_show_returns_message_with_delivery_logs(): void
    {
        $message = Message::factory()
            ->withChannels([Channel::Email, Channel::Slack])
            ->create();

        $response = $this->getJson("/api/messages/{$message->id}");

        $response->assertOk()
            ->assertJsonPath('id', $message->id)
            ->assertJsonPath('title', $message->title)
            ->assertJsonStructure(['id', 'title', 'summary', 'status', 'channels', 'delivery_logs']);
    }

    public function test_show_returns_404_for_nonexistent_message(): void
    {
        $response = $this->getJson('/api/messages/99999');

        $response->assertNotFound();
    }
}
