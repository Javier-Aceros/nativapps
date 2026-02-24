<?php

namespace App\Listeners;

use App\Application\Actions\DispatchToChannelsAction;
use App\Events\MessageProcessed;

/**
 * Listens for MessageProcessed and delegates to DispatchToChannelsAction,
 * which seeds DeliveryLog records and dispatches one SendToChannelJob per channel.
 */
class DispatchChannelsListener
{
    public function __construct(private readonly DispatchToChannelsAction $action) {}

    public function handle(MessageProcessed $event): void
    {
        $this->action->execute($event->message);
    }
}
