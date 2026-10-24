<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\ReminderKind;
use App\Enums\ReminderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reminders are created only by the reminder planner, so nothing is mass assignable.
 *
 * @property int $id
 * @property string $remindable_type
 * @property int $remindable_id
 * @property int $user_id
 * @property ReminderKind $kind
 * @property NotificationChannel $channel
 * @property int $minutes_before
 * @property CarbonImmutable $scheduled_for
 * @property ReminderStatus $status
 * @property string $idempotency_key
 * @property int $attempts
 * @property CarbonImmutable|null $reserved_at
 * @property CarbonImmutable|null $last_attempt_at
 * @property CarbonImmutable|null $sent_at
 * @property string|null $provider_message_id
 * @property string|null $last_error
 * @property string|null $status_reason
 * @property-read CalendarEvent|Task|Presentation|null $remindable
 * @property-read User $user
 */
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReminderKind::class,
            'channel' => NotificationChannel::class,
            'status' => ReminderStatus::class,
            'minutes_before' => 'integer',
            'scheduled_for' => 'datetime',
            'attempts' => 'integer',
            'reserved_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the event, task, or presentation the reminder is for.
     *
     * @return MorphTo<Model, $this>
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
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
     * Get the delivery attempts for the reminder.
     *
     * @return MorphMany<NotificationDelivery, $this>
     */
    public function deliveries(): MorphMany
    {
        return $this->morphMany(NotificationDelivery::class, 'deliverable');
    }
}
