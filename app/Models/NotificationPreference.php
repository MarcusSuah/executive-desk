<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\Role;
use App\Support\AppSettings;
use App\Support\NotificationCategories;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $in_app_enabled
 * @property bool $email_enabled
 * @property bool $sms_enabled
 * @property bool $push_enabled
 * @property array<string, mixed>|null $categories
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
 * @property bool $quiet_hours_enabled
 * @property string $quiet_hours_start
 * @property string $quiet_hours_end
 * @property bool $quiet_hours_allow_high
 * @property string $digest_frequency
 * @property string $digest_time
 * @property int $digest_day
 * @property string $digest_include
 * @property CarbonImmutable|null $last_digest_at
 * @property-read User $user
 */
#[Fillable([
    'in_app_enabled', 'email_enabled', 'sms_enabled', 'push_enabled', 'categories', 'event_reminder_minutes',
    'task_reminder_minutes', 'presentation_reminder_minutes', 'schedule_change_notifications',
    'daily_briefing_enabled', 'daily_briefing_time', 'daily_briefing_channels', 'weekly_briefing_enabled',
    'weekly_briefing_day', 'weekly_briefing_time', 'weekly_briefing_channels', 'quiet_hours_enabled',
    'quiet_hours_start', 'quiet_hours_end', 'quiet_hours_allow_high', 'digest_frequency', 'digest_time',
    'digest_day', 'digest_include',
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
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'categories' => 'array',
            'event_reminder_minutes' => 'array',
            'task_reminder_minutes' => 'array',
            'presentation_reminder_minutes' => 'array',
            'schedule_change_notifications' => 'boolean',
            'daily_briefing_enabled' => 'boolean',
            'daily_briefing_channels' => 'array',
            'weekly_briefing_enabled' => 'boolean',
            'weekly_briefing_day' => 'integer',
            'weekly_briefing_channels' => 'array',
            'quiet_hours_enabled' => 'boolean',
            'quiet_hours_allow_high' => 'boolean',
            'digest_day' => 'integer',
            'last_digest_at' => 'datetime',
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
            'in_app_enabled' => true,
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
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00:00',
            'quiet_hours_end' => '07:00:00',
            'quiet_hours_allow_high' => true,
            'digest_frequency' => 'off',
            'digest_time' => '08:00:00',
            'digest_day' => 1,
            'digest_include' => 'non_critical',
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

    /**
     * Get the effective settings of every category: saved values over defaults.
     *
     * Preferences saved before categories existed are read from the older columns
     * (briefing channels and the schedule-change switch) so nothing changes for them.
     *
     * @return array<string, array<string, bool|string>>
     */
    public function categorySettings(): array
    {
        $settings = NotificationCategories::defaults();

        if ($this->categories === null) {
            foreach (['daily_briefing' => 'daily', 'weekly_briefing' => 'weekly'] as $category => $prefix) {
                $enabled = (bool) $this->getAttribute("{$prefix}_briefing_enabled");
                $channels = (array) $this->getAttribute("{$prefix}_briefing_channels");

                foreach (['mail', 'sms', 'push'] as $channel) {
                    $settings[$category][$channel] = $enabled && in_array($channel, $channels, true);
                }

                $settings[$category]['in_app'] = $enabled;
            }

            if (! $this->schedule_change_notifications) {
                $settings['schedule_changes'] = [...$settings['schedule_changes'], 'mail' => false, 'sms' => false, 'push' => false];
            }

            return $settings;
        }

        foreach ($this->categories as $category => $values) {
            if (isset($settings[$category]) && is_array($values)) {
                $settings[$category] = [...$settings[$category], ...array_intersect_key($values, $settings[$category])];
            }
        }

        return $settings;
    }

    /**
     * Determine whether a category may use a channel ("in_app", "mail", "sms", or "push"),
     * taking the master channel switches into account.
     */
    public function allows(string $category, string $channel): bool
    {
        $master = match ($channel) {
            'in_app' => $this->in_app_enabled,
            'mail' => $this->email_enabled,
            'sms' => $this->sms_enabled,
            'push' => $this->push_enabled,
            default => false,
        };

        return $master && (bool) ($this->categorySettings()[$category][$channel] ?? false);
    }

    /**
     * Get the priority of a category.
     */
    public function priority(string $category): string
    {
        return (string) ($this->categorySettings()[$category]['priority'] ?? 'medium');
    }

    /**
     * Determine whether an email, SMS, or push alert must be held back because of quiet hours.
     */
    public function silencedByQuietHours(string $category, CarbonImmutable $now, string $timezone): bool
    {
        if (! $this->quiet_hours_enabled) {
            return false;
        }

        if ($this->quiet_hours_allow_high && $this->priority($category) === 'high') {
            return false;
        }

        $local = $now->setTimezone($timezone)->format('H:i:s');
        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        // A window such as 22:00–07:00 wraps past midnight.
        return $start <= $end
            ? $local >= $start && $local < $end
            : $local >= $start || $local < $end;
    }

    /**
     * Determine whether an email for this category goes into the digest instead of being sent on its own.
     */
    public function emailsViaDigest(string $category): bool
    {
        if ($this->digest_frequency === 'off' || ! NotificationCategories::digestible($category)) {
            return false;
        }

        $priority = $this->priority($category);

        return $this->digest_include === 'low_only' ? $priority === 'low' : $priority !== 'high';
    }

    /**
     * Keep the briefing columns used by the briefing scheduler in step with the category settings.
     */
    public function syncBriefingColumns(): void
    {
        $settings = $this->categorySettings();

        foreach (['daily_briefing' => 'daily', 'weekly_briefing' => 'weekly'] as $category => $prefix) {
            $channels = array_values(array_filter(['mail', 'sms', 'push'], fn (string $channel) => (bool) $settings[$category][$channel]));

            $this->setAttribute("{$prefix}_briefing_channels", $channels);
            $this->setAttribute("{$prefix}_briefing_enabled", $channels !== [] || (bool) $settings[$category]['in_app']);
        }

        $this->schedule_change_notifications = collect(['in_app', 'mail', 'sms', 'push'])
            ->contains(fn (string $channel) => (bool) $settings['schedule_changes'][$channel]);
    }
}
