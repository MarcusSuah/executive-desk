<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Priority: string
{
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Get the sort weight of the priority (higher is more important).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }

    /**
     * Get the priorities considered high or above.
     *
     * @return array<int, string>
     */
    public static function elevated(): array
    {
        return [self::High->value, self::Urgent->value];
    }
}
