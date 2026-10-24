<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Presentation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => 'presentation',
            'attachable_id' => Presentation::factory(),
            'disk' => 'local',
            'path' => 'attachments/presentation/'.fake()->uuid().'.pdf',
            'original_name' => 'slides.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => hash('sha256', fake()->uuid()),
            'version' => 1,
        ];
    }
}
