<?php

namespace App\Infrastructure\Channels;

use App\Application\DTOs\MessagePayload;
use App\Contracts\NotificationProvider;
use App\Domain\Enums\Channel;
use App\Models\DeliveryLog;
use Illuminate\Support\Facades\Http;

/**
 * Performs a real POST to the configured webhook URL.
 * Posts {title, summary, original_content} as JSON.
 * SLACK_WEBHOOK_URL must be set to a valid Webhook.site / Beeceptor URL.
 */
class SlackChannelAdapter implements NotificationProvider
{
    public function __construct(
        private readonly string $webhookUrl,
    ) {}

    public function supports(): Channel
    {
        return Channel::Slack;
    }

    public function send(MessagePayload $payload, DeliveryLog $log): void
    {
        $body = $payload->toArray();
        $log->update(['payload' => $body]);

        $response = Http::timeout(10)->post($this->webhookUrl, $body);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Slack webhook returned HTTP {$response->status()}: {$response->body()}"
            );
        }

        $log->markSuccess($response->body() ?: 'ok');
    }
}
