<?php

namespace Database\Factories;

use App\Enums\AttendeeResponse;
use App\Enums\AttendeeRole;
use App\Models\CalendarEvent;
use App\Models\EventAttendee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendee>
 */
class EventAttendeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_event_id' => CalendarEvent::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'role' => AttendeeRole::Required,
            'response_status' => AttendeeResponse::Pending,
        ];
    }
}
