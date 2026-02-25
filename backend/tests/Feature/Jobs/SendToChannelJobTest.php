<?php

namespace Tests\Feature\Jobs;

use App\Application\DTOs\MessagePayload;
use App\Contracts\NotificationProvider;
use App\Domain\Enums\Channel;
use App\Domain\Enums\DeliveryStatus;
use App\Infrastructure\Resolvers\ChannelAdapterResolver;
use App\Jobs\SendToChannelJob;
use App\Models\DeliveryLog;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SendToChannelJobTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeMessage(string $summary = 'Test summary'): Message
    {
        return Message::factory()
            ->withChannels([Channel::Email])
            ->create(['summary' => $summary]);
    }

    /**
     * Builds a ChannelAdapterResolver backed by a mock adapter.
     * The mock is pre-configured to support Channel::Email.
     *
     * @return array{0: \Mockery\MockInterface, 1: ChannelAdapterResolver}
     */
    private function mockResolver(?callable $configure = null): array
    {
        $adapter = Mockery::mock(NotificationProvider::class);
        $adapter->shouldReceive('supports')->andReturn(Channel::Email);

        if ($configure) {
            $configure($adapter);
        }

        return [$adapter, new ChannelAdapterResolver([$adapter])];
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_calls_adapter_send_with_correct_payload(): void
    {
        $message = $this->makeMessage('The AI summary');

        [$adapter, $resolver] = $this->mockResolver(function ($adapter) use ($message) {
            $adapter->shouldReceive('send')
                ->once()
                ->withArgs(function (MessagePayload $payload) use ($message) {
                    return $payload->title === $message->title
                        && $payload->summary === 'The AI summary'
                        && $payload->originalContent === $message->original_content;
                });
        });

        (new SendToChannelJob($message->id, Channel::Email))->handle($resolver);
    }

    public function test_creates_delivery_log_if_none_exists(): void
    {
        $message = $this->makeMessage();

        [, $resolver] = $this->mockResolver(
            fn ($a) => $a->shouldReceive('send')->once()
        );

        $this->assertDatabaseMissing('delivery_logs', ['message_id' => $message->id]);

        (new SendToChannelJob($message->id, Channel::Email))->handle($resolver);

        $this->assertDatabaseHas('delivery_logs', [
            'message_id' => $message->id,
            'channel' => Channel::Email->value,
        ]);
    }

    public function test_reuses_existing_delivery_log_without_creating_a_duplicate(): void
    {
        $message = $this->makeMessage();
        DeliveryLog::create([
            'message_id' => $message->id,
            'channel' => Channel::Email,
            'status' => DeliveryStatus::Pending,
        ]);

        [, $resolver] = $this->mockResolver(
            fn ($a) => $a->shouldReceive('send')->once()
        );

        (new SendToChannelJob($message->id, Channel::Email))->handle($resolver);

        $this->assertDatabaseCount('delivery_logs', 1);
    }

    public function test_marks_delivery_log_failed_and_rethrows_on_adapter_exception(): void
    {
        $message = $this->makeMessage();
        $log = DeliveryLog::create([
            'message_id' => $message->id,
            'channel' => Channel::Email,
            'status' => DeliveryStatus::Pending,
        ]);

        [, $resolver] = $this->mockResolver(
            fn ($a) => $a->shouldReceive('send')
                ->andThrow(new RuntimeException('Connection refused'))
        );

        try {
            (new SendToChannelJob($message->id, Channel::Email))->handle($resolver);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Connection refused', $e->getMessage());
        }

        $this->assertSame(DeliveryStatus::Failed, $log->fresh()->status);
        $this->assertSame('Connection refused', $log->fresh()->error_message);
        $this->assertSame('network_error', $log->fresh()->error_code);
    }

    public function test_classifies_http_404_exception_as_config_error(): void
    {
        $message = $this->makeMessage();
        $log = DeliveryLog::create([
            'message_id' => $message->id,
            'channel' => Channel::Email,
            'status' => DeliveryStatus::Pending,
        ]);

        // Simulates a webhook returning HTTP 404 (e.g. placeholder URL with invalid token).
        [, $resolver] = $this->mockResolver(
            fn ($a) => $a->shouldReceive('send')
                ->andThrow(new RuntimeException('Slack webhook returned HTTP 404: {"success":false,"error":{"message":"Token not found"}}'))
        );

        try {
            (new SendToChannelJob($message->id, Channel::Email))->handle($resolver);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('config_error', $log->fresh()->error_code);
    }

    public function test_classifies_http_401_exception_as_config_error(): void
    {
        $message = $this->makeMessage();
        $log = DeliveryLog::create([
            'message_id' => $message->id,
            'channel' => Channel::Email,
            'status' => DeliveryStatus::Pending,
        ]);

        [, $resolver] = $this->mockResolver(
            fn ($a) => $a->shouldReceive('send')
                ->andThrow(new RuntimeException('Slack webhook returned HTTP 401: Unauthorized'))
        );

        try {
            (new SendToChannelJob($message->id, Channel::Email))->handle($resolver);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('config_error', $log->fresh()->error_code);
    }

    public function test_classifies_http_500_exception_as_channel_error(): void
    {
        $message = $this->makeMessage();
        $log = DeliveryLog::create([
            'message_id' => $message->id,
            'channel' => Channel::Email,
            'status' => DeliveryStatus::Pending,
        ]);

        [, $resolver] = $this->mockResolver(
            fn ($a) => $a->shouldReceive('send')
                ->andThrow(new RuntimeException('Slack webhook returned HTTP 500: Internal Server Error'))
        );

        try {
            (new SendToChannelJob($message->id, Channel::Email))->handle($resolver);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('channel_error', $log->fresh()->error_code);
    }

    public function test_failed_channel_does_not_affect_other_channels(): void
    {
        // Email fails — Slack job should be independent and unaffected.
        $message = Message::factory()
            ->withChannels([Channel::Email, Channel::Slack])
            ->create();

        $failingAdapter = Mockery::mock(NotificationProvider::class);
        $failingAdapter->shouldReceive('supports')->andReturn(Channel::Email);
        $failingAdapter->shouldReceive('send')->andThrow(new RuntimeException('Email down'));

        $successAdapter = Mockery::mock(NotificationProvider::class);
        $successAdapter->shouldReceive('supports')->andReturn(Channel::Slack);
        $successAdapter->shouldReceive('send')->once(); // Must still be called

        $resolver = new ChannelAdapterResolver([$failingAdapter, $successAdapter]);

        // Email job fails
        try {
            (new SendToChannelJob($message->id, Channel::Email))->handle($resolver);
        } catch (RuntimeException) {
            // expected
        }

        // Slack job runs independently — no exception expected
        (new SendToChannelJob($message->id, Channel::Slack))->handle($resolver);
    }
}
