<?php

namespace Tests\Feature\Infrastructure\Channels;

use App\Application\DTOs\MessagePayload;
use App\Domain\Enums\Channel;
use App\Domain\Enums\DeliveryStatus;
use App\Infrastructure\Channels\SlackChannelAdapter;
use App\Models\DeliveryLog;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SlackChannelAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.example.com/test-webhook';

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeLog(): DeliveryLog
    {
        return DeliveryLog::create([
            'message_id' => Message::factory()->create()->id,
            'channel' => Channel::Slack,
            'status' => DeliveryStatus::Pending,
        ]);
    }

    private function adapter(): SlackChannelAdapter
    {
        return new SlackChannelAdapter(self::WEBHOOK);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_posts_to_the_configured_webhook_url(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $this->adapter()->send(
            new MessagePayload('T', 'S', 'C'),
            $this->makeLog()
        );

        Http::assertSent(fn ($req) => $req->url() === self::WEBHOOK);
    }

    public function test_marks_delivery_log_as_success_on_200(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $log = $this->makeLog();
        $this->adapter()->send(new MessagePayload('T', 'S', 'C'), $log);

        $this->assertSame(DeliveryStatus::Success, $log->fresh()->status);
    }

    public function test_stores_webhook_response_on_log(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $log = $this->makeLog();
        $this->adapter()->send(new MessagePayload('T', 'S', 'C'), $log);

        $this->assertSame('ok', $log->fresh()->response);
    }

    public function test_throws_runtime_exception_on_non_2xx_response(): void
    {
        Http::fake([self::WEBHOOK => Http::response('channel_not_found', 404)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Slack webhook returned HTTP 404');

        $this->adapter()->send(new MessagePayload('T', 'S', 'C'), $this->makeLog());
    }

    public function test_throws_on_server_error(): void
    {
        Http::fake([self::WEBHOOK => Http::response('Internal Server Error', 500)]);

        $this->expectException(RuntimeException::class);

        $this->adapter()->send(new MessagePayload('T', 'S', 'C'), $this->makeLog());
    }

    public function test_payload_contains_title_and_summary(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $log = $this->makeLog();
        $this->adapter()->send(
            new MessagePayload('My Title', 'My Summary', 'My Content'),
            $log
        );

        $stored = $log->fresh()->payload;
        $this->assertSame('My Title', $stored['title']);
        $this->assertSame('My Summary', $stored['summary']);
    }

    public function test_payload_contains_original_content(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $log = $this->makeLog();
        $this->adapter()->send(
            new MessagePayload('T', 'S', 'Full original content'),
            $log
        );

        $this->assertSame('Full original content', $log->fresh()->payload['original_content']);
    }

    public function test_request_body_is_json(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $this->adapter()->send(new MessagePayload('T', 'S', 'C'), $this->makeLog());

        Http::assertSent(fn ($req) => $req->hasHeader('Content-Type', 'application/json'));
    }
}
