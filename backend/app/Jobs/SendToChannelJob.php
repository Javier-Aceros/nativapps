<?php

namespace App\Jobs;

use App\Application\DTOs\MessagePayload;
use App\Domain\Enums\Channel;
use App\Domain\Enums\DeliveryStatus;
use App\Infrastructure\Resolvers\ChannelAdapterResolver;
use App\Models\DeliveryLog;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a message to a single channel. Each channel runs as an independent
 * job so a failure in one (e.g. Slack timeout) never blocks the others.
 */
class SendToChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Laravel will retry failed attempts up to this limit. */
    public int $tries = 3;

    public function __construct(
        private readonly int $messageId,
        private readonly Channel $channel,
    ) {}

    public function handle(ChannelAdapterResolver $resolver): void
    {
        $message = Message::findOrFail($this->messageId);

        $log = DeliveryLog::firstOrCreate(
            ['message_id' => $message->id, 'channel' => $this->channel],
            ['status' => DeliveryStatus::Pending],
        );

        $payload = new MessagePayload(
            title: $message->title,
            summary: $message->summary,
            originalContent: $message->original_content,
        );

        try {
            $adapter = $resolver->resolve($this->channel);
            $adapter->send($payload, $log);
        } catch (\Throwable $e) {
            Log::error("[{$this->channel->value}] Channel delivery failed", [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);

            $log->markFailed($e->getMessage());

            // Re-throw so Laravel's queue retries the job up to $tries times.
            throw $e;
        }
    }
}
