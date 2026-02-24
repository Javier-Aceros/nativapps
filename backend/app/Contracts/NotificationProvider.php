<?php

namespace App\Contracts;

use App\Application\DTOs\MessagePayload;
use App\Domain\Enums\Channel;
use App\Models\DeliveryLog;

interface NotificationProvider
{
    /**
     * Send the payload through this channel and update the delivery log.
     * Throw any exception on failure — the caller (Job) handles resilience.
     */
    public function send(MessagePayload $payload, DeliveryLog $log): void;

    /** Returns the channel this adapter handles. */
    public function supports(): Channel;
}
