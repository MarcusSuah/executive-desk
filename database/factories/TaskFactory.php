<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Review budget proposal', 'Prepare board presentation', 'Follow up with client', 'Approve travel plan', 'Sign vendor contract']),
            'description' => fake()->optional()->sentence(),
            'assigned_to' => User::factory()->executive(),
            'created_by' => null,
            'priority' => Priority::Normal,
            'status' => TaskStatus::Pending,
            'due_at' => now()->addDays(fake()->numberBetween(1, 10))->setTime(17, 0),
            'timezone' => 'UTC',
        ];
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the task is past due and still open.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Pending,
            'due_at' => now()->subDay(),
        ]);
    }
}
