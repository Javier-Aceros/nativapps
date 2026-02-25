<?php

namespace Tests\Feature\Infrastructure\Channels;

use App\Application\DTOs\MessagePayload;
use App\Domain\Enums\Channel;
use App\Domain\Enums\DeliveryStatus;
use App\Infrastructure\Channels\EmailChannelAdapter;
use App\Models\DeliveryLog;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EmailChannelAdapterTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeLog(): DeliveryLog
    {
        return DeliveryLog::create([
            'message_id' => Message::factory()->create()->id,
            'channel' => Channel::Email,
            'status' => DeliveryStatus::Pending,
        ]);
    }

    private function makePayload(): MessagePayload
    {
        return new MessagePayload('The Title', 'Executive summary', 'Full original content.');
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_marks_delivery_log_as_success(): void
    {
        Log::spy();

        $log = $this->makeLog();
        (new EmailChannelAdapter)->send($this->makePayload(), $log);

        $this->assertSame(DeliveryStatus::Success, $log->fresh()->status);
    }

    public function test_response_is_stored_on_the_log(): void
    {
        Log::spy();

        $log = $this->makeLog();
        (new EmailChannelAdapter)->send($this->makePayload(), $log);

        $this->assertNotNull($log->fresh()->response);
    }

    public function test_full_payload_is_stored_on_the_log(): void
    {
        Log::spy();

        $log = $this->makeLog();
        (new EmailChannelAdapter)->send($this->makePayload(), $log);

        $stored = $log->fresh()->payload;
        $this->assertSame('The Title', $stored['title']);
        $this->assertSame('Executive summary', $stored['summary']);
        $this->assertSame('Full original content.', $stored['original_content']);
    }

    public function test_dispatches_info_log_with_email_tag(): void
    {
        Log::spy();

        (new EmailChannelAdapter)->send($this->makePayload(), $this->makeLog());

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, '[EMAIL]'));
    }

    public function test_log_contains_all_payload_fields(): void
    {
        Log::spy();

        (new EmailChannelAdapter)->send($this->makePayload(), $this->makeLog());

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $msg, array $ctx) => ($ctx['title'] ?? null) === 'The Title' &&
                ($ctx['summary'] ?? null) === 'Executive summary' &&
                ($ctx['original_content'] ?? null) === 'Full original content.'
        );
    }
}
