<?php

namespace Database\Factories;

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presentation>
 */
class PresentationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $presentationAt = now()->addDays(fake()->numberBetween(5, 20))->setTime(10, 0);

        return [
            'owner_id' => User::factory()->executive(),
            'title' => fake()->randomElement(['Quarterly Results', 'Annual Strategy', 'Investor Update', 'Product Roadmap']),
            'description' => fake()->optional()->sentence(),
            'presentation_at' => $presentationAt,
            'timezone' => 'UTC',
            'venue' => 'Board Room',
            'preparation_deadline_at' => $presentationAt->subDays(3),
            'review_deadline_at' => $presentationAt->subDay(),
            'status' => PresentationStatus::InPreparation,
        ];
    }
}
