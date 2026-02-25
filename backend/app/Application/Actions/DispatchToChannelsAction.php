<?php

namespace App\Application\Actions;

use App\Domain\Enums\DeliveryStatus;
use App\Domain\Enums\MessageStatus;
use App\Jobs\SendToChannelJob;
use App\Models\DeliveryLog;
use App\Models\Message;

/**
 * Transitions the message to "processing", seeds a DeliveryLog record for
 * each selected channel, then dispatches an independent SendToChannelJob
 * per channel so failures are isolated.
 */
class DispatchToChannelsAction
{
    public function execute(Message $message): void
    {
        $message->update(['status' => MessageStatus::Processing]);

        foreach ($message->selectedChannels() as $channel) {
            $attempt = DeliveryLog::where('message_id', $message->id)
                ->where('channel', $channel)
                ->max('attempt') ?? 0;

            $log = DeliveryLog::create([
                'message_id' => $message->id,
                'channel' => $channel,
                'attempt' => $attempt + 1,
                'status' => DeliveryStatus::Pending,
            ]);

            SendToChannelJob::dispatch($message->id, $channel, $log->id);
        }
    }
}
