<?php

namespace App\Jobs;

use App\Application\DTOs\MessagePayload;
use App\Domain\Enums\Channel;
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
 *
 * Retry policy: 3 attempts with progressive backoff (10 s → 60 s → 180 s).
 * A channel job failing does NOT affect sibling jobs for other channels.
 */
class SendToChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum attempts before the job is moved to failed_jobs. */
    public int $tries = 3;

    /** Seconds before the worker considers the job timed out. */
    public int $timeout = 30;

    public function __construct(
        private readonly int $messageId,
        private readonly Channel $channel,
        private readonly int $logId,
    ) {}

    /**
     * Seconds to wait before each retry attempt (progressive backoff).
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(ChannelAdapterResolver $resolver): void
    {
        $message = Message::findOrFail($this->messageId);

        $log = DeliveryLog::findOrFail($this->logId);

        $payload = new MessagePayload(
            title: $message->title,
            summary: $message->summary,
            originalContent: $message->original_content,
        );

        try {
            $adapter = $resolver->resolve($this->channel);
            $adapter->send($payload, $log);
        } catch (\Throwable $e) {
            $errorCode = $this->classifyException($e);

            Log::error("[{$this->channel->value}] Channel delivery failed", [
                'message_id' => $this->messageId,
                'attempt' => $this->attempts(),
                'error_code' => $errorCode,
                'error' => $e->getMessage(),
            ]);

            $log->markFailed($e->getMessage(), $errorCode);

            // Re-throw so Laravel's queue retries the job up to $tries times.
            throw $e;
        }
    }

    /**
     * Called by Laravel after all retry attempts are exhausted.
     * Ensures the DeliveryLog is definitively marked as failed.
     */
    public function failed(\Throwable $e): void
    {
        $errorCode = $this->classifyException($e);

        Log::error("[{$this->channel->value}] Job permanently failed after {$this->tries} attempts", [
            'message_id' => $this->messageId,
            'error_code' => $errorCode,
            'error' => $e->getMessage(),
        ]);

        DeliveryLog::find($this->logId)?->markFailed($e->getMessage(), $errorCode);
    }

    /**
     * Maps a throwable to a short, stable error code stored in delivery_logs.
     * The frontend translates these codes to user-friendly labels.
     *
     * Codes:
     *   network_error  — TCP/connection failure or timeout
     *   config_error   — missing or invalid channel configuration (bad URL, wrong token, auth failure)
     *   channel_error  — the remote channel returned an unexpected server error (default)
     */
    private function classifyException(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'network_error';
        }

        $message = strtolower($e->getMessage());

        if (str_contains($message, 'connection refused')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')) {
            return 'network_error';
        }

        // Auth/routing HTTP errors always mean the channel is misconfigured:
        //   401 → wrong API key / token
        //   403 → insufficient permissions
        //   404 → URL or token not found (e.g. placeholder webhook URL)
        if (preg_match('/http (\d{3})/', $message, $matches)) {
            $status = (int) $matches[1];
            if (in_array($status, [401, 403, 404], strict: true)) {
                return 'config_error';
            }
        }

        if (str_contains($message, 'not configured')
            || str_contains($message, 'webhook url is empty')) {
            return 'config_error';
        }

        return 'channel_error';
    }
}
