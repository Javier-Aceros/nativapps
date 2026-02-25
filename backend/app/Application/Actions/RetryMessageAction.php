<?php

namespace App\Application\Actions;

use App\Contracts\AiProvider;
use App\Domain\Enums\DeliveryStatus;
use App\Domain\Enums\MessageStatus;
use App\Domain\ValueObjects\Summary;
use App\Events\MessageProcessed;
use App\Exceptions\AiProcessingException;
use App\Models\DeliveryLog;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * Retries the full processing flow for a failed message:
 *   1. Clears existing delivery logs.
 *   2. Re-runs AI summarization on the original content.
 *   3. On success: updates the message and fires MessageProcessed.
 *   4. On failure: marks the message as failed again (no exception thrown to caller).
 */
class RetryMessageAction
{
    public function __construct(private readonly AiProvider $aiProvider) {}

    /**
     * @throws \RuntimeException if the message is currently being processed.
     */
    public function execute(Message $message): Message
    {
        // Guard: refuse retry while already processing
        if ($message->status === MessageStatus::Processing) {
            throw new \RuntimeException('Message is currently being processed.');
        }

        // Step 2 – Re-summarize using the stored original content
        $content = $message->original_content;

        if (mb_strlen(trim($content)) <= Summary::MAX_LENGTH) {
            $summary = Summary::fromString($content);
        } else {
            try {
                $summary = $this->aiProvider->summarize($content);
            } catch (\RuntimeException $e) {
                $exception = AiProcessingException::fromThrowable($e);

                Log::error('AI provider failed during message retry', [
                    'error_code' => $exception->errorCode,
                    'provider' => config('services.ai.provider'),
                    'message_id' => $message->id,
                    'title' => $message->title,
                    'exception' => $e->getMessage(),
                ]);

                $message->update([
                    'status' => MessageStatus::Failed,
                    'summary' => null,
                ]);

                foreach ($message->selectedChannels() as $channel) {
                    $attempt = $message->deliveryLogs()
                        ->where('channel', $channel)
                        ->max('attempt') ?? 0;

                    DeliveryLog::create([
                        'message_id' => $message->id,
                        'channel' => $channel,
                        'attempt' => $attempt + 1,
                        'status' => DeliveryStatus::Failed,
                        'error_code' => $exception->errorCode,
                    ]);
                }

                // Return failed message without throwing – controller returns 200
                // so the frontend can update the display with the new failed state.
                return $message->fresh(['deliveryLogs']);
            }
        }

        // Step 3 – Update message with new summary; dispatch channels
        $message->update([
            'summary' => $summary->value(),
            'status' => MessageStatus::Pending,
        ]);

        // DispatchChannelsListener will create fresh DeliveryLogs and dispatch jobs
        MessageProcessed::dispatch($message->fresh());

        return $message->fresh(['deliveryLogs']);
    }
}
