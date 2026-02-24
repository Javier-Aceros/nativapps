<?php

namespace App\Models;

use App\Domain\Enums\Channel;
use App\Domain\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'channel',
        'status',
        'payload',
        'response',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'status' => DeliveryStatus::class,
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function markSuccess(string $response): void
    {
        $this->update([
            'status' => DeliveryStatus::Success,
            'response' => $response,
            'sent_at' => now(),
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => DeliveryStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }
}
