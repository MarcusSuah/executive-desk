<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'in_app_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'push_enabled' => false,
            'event_reminder_minutes' => [60, 30, 15, 5],
            'task_reminder_minutes' => [1440, 60],
            'presentation_reminder_minutes' => [2880, 1440],
            'schedule_change_notifications' => true,
            'daily_briefing_enabled' => false,
            'daily_briefing_time' => '07:00:00',
            'daily_briefing_channels' => ['mail'],
            'weekly_briefing_enabled' => false,
            'weekly_briefing_day' => 1,
            'weekly_briefing_time' => '07:00:00',
            'weekly_briefing_channels' => ['mail'],
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
}
