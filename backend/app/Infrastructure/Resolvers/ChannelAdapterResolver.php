<?php

namespace App\Infrastructure\Resolvers;

use App\Contracts\NotificationProvider;
use App\Domain\Enums\Channel;

/**
 * Strategy Resolver: maps each Channel to its registered NotificationProvider.
 *
 * Adding a new channel is "Plug & Play":
 *   1. Create a class implementing NotificationProvider.
 *   2. Register it in AppServiceProvider — no other changes needed.
 */
class ChannelAdapterResolver
{
    /** @var array<string, NotificationProvider> */
    private array $map = [];

    /** @param iterable<NotificationProvider> $adapters */
    public function __construct(iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $this->map[$adapter->supports()->value] = $adapter;
        }
    }

    public function resolve(Channel $channel): NotificationProvider
    {
        if (! isset($this->map[$channel->value])) {
            throw new \InvalidArgumentException(
                "No adapter registered for channel [{$channel->value}]."
            );
        }

        return $this->map[$channel->value];
    }
}
