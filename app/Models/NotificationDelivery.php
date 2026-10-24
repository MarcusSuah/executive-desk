<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single delivery attempt. Rows are written by the delivery recorder only.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $deliverable_type
 * @property int|null $deliverable_id
 * @property string $notification_type
 * @property NotificationChannel $channel
 * @property DeliveryStatus $status
 * @property int $attempt_number
 * @property CarbonImmutable|null $scheduled_for
 * @property CarbonImmutable $attempted_at
 * @property string|null $provider_message_id
 * @property string|null $error_message
 * @property-read User|null $user
 */
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => DeliveryStatus::class,
            'attempt_number' => 'integer',
            'scheduled_for' => 'datetime',
            'attempted_at' => 'datetime',
        ];
    }

    /**
     * Get the recipient.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reminder, briefing, or event the delivery was for.
     *
     * @return MorphTo<Model, $this>
     */
    public function deliverable(): MorphTo
    {
        return $this->morphTo();
    }
}
