<?php

namespace Database\Factories;

use App\Domain\Enums\Channel;
use App\Domain\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4, false),
            'original_content' => fake()->paragraphs(2, true),
            'summary' => substr(fake()->sentence(10, false), 0, 100),
            'channels' => [Channel::Email->value],
            'status' => MessageStatus::Pending->value,
        ];
    }

    /**
     * Override the selected channels.
     *
     * @param  Channel[]  $channels
     */
    public function withChannels(array $channels): static
    {
        return $this->state(fn () => [
            'channels' => array_map(
                fn (Channel $c) => $c->value,
                $channels
            ),
        ]);
    }
}
