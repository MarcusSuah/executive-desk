<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\ReminderKind;
use App\Enums\ReminderStatus;
use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'remindable_type' => 'calendar_event',
            'remindable_id' => CalendarEvent::factory(),
            'user_id' => User::factory(),
            'kind' => ReminderKind::EventStart,
            'channel' => NotificationChannel::Mail,
            'minutes_before' => 15,
            'scheduled_for' => now()->addHour(),
            'status' => ReminderStatus::Pending,
            'idempotency_key' => hash('sha256', fake()->uuid()),
            'attempts' => 0,
        ];
    }
}
