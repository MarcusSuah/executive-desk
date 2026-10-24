<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TaskStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get the statuses that represent unfinished work.
     *
     * @return array<int, string>
     */
    public static function open(): array
    {
        return [self::Pending->value, self::InProgress->value];
    }
}
