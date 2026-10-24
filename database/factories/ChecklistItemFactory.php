<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklistable_type' => 'task',
            'checklistable_id' => Task::factory(),
            'title' => fake()->sentence(4),
            'is_completed' => false,
            'position' => 0,
        ];
    }
}
