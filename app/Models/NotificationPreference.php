<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\Role;
use App\Support\AppSettings;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $email_enabled
 * @property bool $sms_enabled
 * @property bool $push_enabled
 * @property array<int, int> $event_reminder_minutes
 * @property array<int, int> $task_reminder_minutes
 * @property array<int, int> $presentation_reminder_minutes
 * @property bool $schedule_change_notifications
 * @property bool $daily_briefing_enabled
 * @property string $daily_briefing_time
 * @property array<int, string> $daily_briefing_channels
 * @property bool $weekly_briefing_enabled
 * @property int $weekly_briefing_day
 * @property string $weekly_briefing_time
 * @property array<int, string> $weekly_briefing_channels
 * @property-read User $user
 */
#[Fillable([
    'email_enabled', 'sms_enabled', 'push_enabled', 'event_reminder_minutes', 'task_reminder_minutes',
    'presentation_reminder_minutes', 'schedule_change_notifications', 'daily_briefing_enabled',
    'daily_briefing_time', 'daily_briefing_channels', 'weekly_briefing_enabled', 'weekly_briefing_day',
    'weekly_briefing_time', 'weekly_briefing_channels',
])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'event_reminder_minutes' => 'array',
            'task_reminder_minutes' => 'array',
            'presentation_reminder_minutes' => 'array',
            'schedule_change_notifications' => 'boolean',
            'daily_briefing_enabled' => 'boolean',
            'daily_briefing_channels' => 'array',
            'weekly_briefing_enabled' => 'boolean',
            'weekly_briefing_day' => 'integer',
            'weekly_briefing_channels' => 'array',
        ];
    }

    /**
     * Get the default preference attributes for a new user.
     *
     * @return array<string, mixed>
     */
    public static function defaultAttributesFor(User $user): array
    {
        $isExecutive = $user->hasRole(Role::Executive->value);

        return [
            'email_enabled' => true,
            'sms_enabled' => false,
            'push_enabled' => false,
            'event_reminder_minutes' => AppSettings::defaultEventReminderMinutes(),
            'task_reminder_minutes' => AppSettings::defaultTaskReminderMinutes(),
            'presentation_reminder_minutes' => AppSettings::defaultPresentationReminderMinutes(),
            'schedule_change_notifications' => true,
            'daily_briefing_enabled' => $isExecutive,
            'daily_briefing_time' => '07:00:00',
            'daily_briefing_channels' => [NotificationChannel::Mail->value],
            'weekly_briefing_enabled' => $isExecutive,
            'weekly_briefing_day' => 1,
            'weekly_briefing_time' => '07:00:00',
            'weekly_briefing_channels' => [NotificationChannel::Mail->value],
        ];
    }

    /**
     * Get the user the preferences belong to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
