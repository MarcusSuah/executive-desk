<?php

namespace Database\Factories;

use App\Enums\BriefingStatus;
use App\Enums\BriefingType;
use App\Models\ExecutiveBriefing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExecutiveBriefing>
 */
class ExecutiveBriefingFactory extends Factory
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
            'type' => BriefingType::Daily,
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'timezone' => 'UTC',
            'content' => ['summary' => [], 'sections' => []],
            'generated_at' => now(),
            'status' => BriefingStatus::Generated,
        ];
    }
}
