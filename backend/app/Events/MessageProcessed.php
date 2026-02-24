<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after the AI has generated the summary and the Message record is saved.
 * Listeners dispatch one SendToChannelJob per selected channel.
 */
class MessageProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}
}
