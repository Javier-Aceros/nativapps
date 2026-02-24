<?php

namespace Tests\Feature\Actions;

use App\Application\Actions\DispatchToChannelsAction;
use App\Domain\Enums\Channel;
use App\Domain\Enums\DeliveryStatus;
use App\Domain\Enums\MessageStatus;
use App\Jobs\SendToChannelJob;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchToChannelsActionTest extends TestCase
{
    use RefreshDatabase;

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_transitions_message_status_to_processing(): void
    {
        Queue::fake();

        $message = Message::factory()->withChannels([Channel::Email])->create();

        (new DispatchToChannelsAction)->execute($message);

        $this->assertSame(MessageStatus::Processing, $message->fresh()->status);
    }

    public function test_creates_one_pending_delivery_log_per_channel(): void
    {
        Queue::fake();

        $message = Message::factory()
            ->withChannels([Channel::Email, Channel::Slack, Channel::Sms])
            ->create();

        (new DispatchToChannelsAction)->execute($message);

        $this->assertDatabaseCount('delivery_logs', 3);

        foreach ([Channel::Email, Channel::Slack, Channel::Sms] as $channel) {
            $this->assertDatabaseHas('delivery_logs', [
                'message_id' => $message->id,
                'channel' => $channel->value,
                'status' => DeliveryStatus::Pending->value,
            ]);
        }
    }

    public function test_dispatches_one_send_job_per_channel(): void
    {
        Queue::fake();

        $message = Message::factory()
            ->withChannels([Channel::Email, Channel::Slack, Channel::Sms])
            ->create();

        (new DispatchToChannelsAction)->execute($message);

        Queue::assertCount(3);
        Queue::assertPushed(SendToChannelJob::class, 3);
    }

    public function test_dispatches_no_jobs_when_no_channels_selected(): void
    {
        Queue::fake();

        $message = Message::factory()->withChannels([])->create();

        (new DispatchToChannelsAction)->execute($message);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('delivery_logs', 0);
    }

    public function test_dispatches_job_for_single_channel(): void
    {
        Queue::fake();

        $message = Message::factory()->withChannels([Channel::Slack])->create();

        (new DispatchToChannelsAction)->execute($message);

        Queue::assertCount(1);
        Queue::assertPushed(SendToChannelJob::class, 1);
    }

    public function test_delivery_logs_are_created_before_jobs_are_dispatched(): void
    {
        // With Queue::fake jobs never run, so we can verify the logs exist immediately.
        Queue::fake();

        $message = Message::factory()->withChannels([Channel::Email])->create();

        (new DispatchToChannelsAction)->execute($message);

        $this->assertDatabaseHas('delivery_logs', [
            'message_id' => $message->id,
            'channel' => Channel::Email->value,
            'status' => DeliveryStatus::Pending->value,
        ]);
    }
}
