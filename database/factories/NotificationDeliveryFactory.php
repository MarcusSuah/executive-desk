<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
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
            'notification_type' => 'event_reminder',
            'channel' => NotificationChannel::Mail,
            'status' => DeliveryStatus::Sent,
            'attempt_number' => 1,
            'attempted_at' => now(),
            'provider_message_id' => fake()->uuid(),
        ];
    }
}
