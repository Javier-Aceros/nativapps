<?php

namespace App\Models;

use App\Domain\Enums\Channel;
use App\Domain\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'original_content',
        'summary',
        'channels',
        'status',
    ];

    protected $casts = [
        'channels' => 'array',
        'status' => MessageStatus::class,
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Returns Channel enums selected for this message. */
    public function selectedChannels(): array
    {
        return array_map(
            fn (string $value) => Channel::from($value),
            $this->channels ?? []
        );
    }

    /** Returns the DeliveryLog for a given channel, if it exists. */
    public function logForChannel(Channel $channel): ?DeliveryLog
    {
        return $this->deliveryLogs
            ->firstWhere('channel', $channel->value);
    }
}
