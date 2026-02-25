<?php

namespace App\Application\Actions;

use App\Domain\Enums\Channel;
use App\Domain\Enums\DeliveryStatus;
use App\Jobs\SendToChannelJob;
use App\Models\DeliveryLog;
use App\Models\Message;

/**
 * Retries delivery to a single channel for an already-processed message.
 * The message must have a valid AI summary; the latest delivery log for that
 * channel must not already be pending.
 * Each retry creates a new DeliveryLog row (attempt + 1) so the full history
 * of attempts is preserved.
 */
class RetryChannelAction
{
    /**
     * @throws \RuntimeException if the channel is already pending or the message has no summary.
     */
    public function execute(Message $message, Channel $channel): DeliveryLog
    {
        // Guard: message must have a valid summary (AI succeeded)
        if ($message->summary === null) {
            throw new \RuntimeException('Message has no AI summary. Retry the full message instead.');
        }

        // Guard: prevent concurrent retry of the same channel
        $latestLog = $message->deliveryLogs()
            ->where('channel', $channel->value)
            ->orderByDesc('attempt')
            ->first();

        if ($latestLog?->status === DeliveryStatus::Pending) {
            throw new \RuntimeException('Channel is already being retried.');
        }

        $attempt = $latestLog?->attempt ?? 0;

        $log = DeliveryLog::create([
            'message_id' => $message->id,
            'channel' => $channel,
            'attempt' => $attempt + 1,
            'status' => DeliveryStatus::Pending,
        ]);

        SendToChannelJob::dispatch($message->id, $channel, $log->id);

        return $log;
    }
}
