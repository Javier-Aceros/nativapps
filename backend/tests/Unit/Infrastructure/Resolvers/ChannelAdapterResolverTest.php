<?php

namespace Tests\Unit\Infrastructure\Resolvers;

use App\Application\DTOs\MessagePayload;
use App\Contracts\NotificationProvider;
use App\Domain\Enums\Channel;
use App\Infrastructure\Resolvers\ChannelAdapterResolver;
use App\Models\DeliveryLog;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ChannelAdapterResolverTest extends TestCase
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Creates an anonymous NotificationProvider stub for the given channel. */
    private function stubAdapter(Channel $channel): NotificationProvider
    {
        return new class($channel) implements NotificationProvider
        {
            public function __construct(private readonly Channel $ch) {}

            public function supports(): Channel
            {
                return $this->ch;
            }

            public function send(MessagePayload $payload, DeliveryLog $log): void {}
        };
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_resolves_correct_adapter_for_email(): void
    {
        $adapter = $this->stubAdapter(Channel::Email);
        $resolver = new ChannelAdapterResolver([$adapter]);

        $this->assertSame($adapter, $resolver->resolve(Channel::Email));
    }

    public function test_resolves_correct_adapter_for_slack(): void
    {
        $adapter = $this->stubAdapter(Channel::Slack);
        $resolver = new ChannelAdapterResolver([$adapter]);

        $this->assertSame($adapter, $resolver->resolve(Channel::Slack));
    }

    public function test_resolves_correct_adapter_for_sms(): void
    {
        $adapter = $this->stubAdapter(Channel::Sms);
        $resolver = new ChannelAdapterResolver([$adapter]);

        $this->assertSame($adapter, $resolver->resolve(Channel::Sms));
    }

    public function test_resolves_each_adapter_independently_when_all_registered(): void
    {
        $email = $this->stubAdapter(Channel::Email);
        $slack = $this->stubAdapter(Channel::Slack);
        $sms = $this->stubAdapter(Channel::Sms);
        $resolver = new ChannelAdapterResolver([$email, $slack, $sms]);

        $this->assertSame($email, $resolver->resolve(Channel::Email));
        $this->assertSame($slack, $resolver->resolve(Channel::Slack));
        $this->assertSame($sms, $resolver->resolve(Channel::Sms));
    }

    public function test_throws_for_unregistered_channel(): void
    {
        $resolver = new ChannelAdapterResolver([]); // empty registry

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No adapter registered for channel [email].');

        $resolver->resolve(Channel::Email);
    }

    public function test_throws_when_only_other_channels_are_registered(): void
    {
        $resolver = new ChannelAdapterResolver([
            $this->stubAdapter(Channel::Slack),
            $this->stubAdapter(Channel::Sms),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve(Channel::Email);
    }

    public function test_last_registered_adapter_wins_on_duplicate_channel(): void
    {
        $first = $this->stubAdapter(Channel::Email);
        $second = $this->stubAdapter(Channel::Email);
        $resolver = new ChannelAdapterResolver([$first, $second]);

        $this->assertSame($second, $resolver->resolve(Channel::Email));
    }
}
