<?php

namespace App\Infrastructure\Channels;

use App\Application\DTOs\MessagePayload;
use App\Contracts\NotificationProvider;
use App\Domain\Enums\Channel;
use App\Models\DeliveryLog;
use Illuminate\Support\Facades\Log;

/**
 * Simulates a REST email dispatch.
 * Logs the full payload to laravel.log as evidence (no real HTTP call).
 */
class EmailChannelAdapter implements NotificationProvider
{
    public function supports(): Channel
    {
        return Channel::Email;
    }

    public function send(MessagePayload $payload, DeliveryLog $log): void
    {
        $body = $payload->toArray();
        $log->update(['payload' => $body]);

        Log::info('[EMAIL] Simulated REST dispatch', [
            'title' => $payload->title,
            'summary' => $payload->summary,
            'original_content' => $payload->originalContent,
        ]);

        $log->markSuccess('Email simulated — payload logged to laravel.log.');
    }
}
