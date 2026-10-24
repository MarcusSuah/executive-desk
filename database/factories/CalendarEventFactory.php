<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\Priority;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 14))->setTime(fake()->numberBetween(8, 16), 0);

        return [
            'owner_id' => User::factory()->executive(),
            'created_by' => null,
            'title' => fake()->randomElement(['Board Meeting', 'Client Presentation', 'Team Strategy Review', 'Budget Review', 'Partner Lunch']),
            'description' => fake()->optional()->sentence(),
            'type' => EventType::InternalMeeting,
            'priority' => Priority::Normal,
            'status' => EventStatus::Scheduled,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'timezone' => 'UTC',
            'all_day' => false,
            'location' => fake()->optional()->randomElement(['Conference Room A', 'Board Room', 'Head Office, 12th Floor']),
            'meeting_url' => null,
            'agenda' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Schedule the event at a specific UTC time with the given duration in minutes.
     */
    public function at(DateTimeInterface $startsAt, int $minutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => CarbonImmutable::instance($startsAt)->addMinutes($minutes),
        ]);
    }

    /**
     * Indicate that the event was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
