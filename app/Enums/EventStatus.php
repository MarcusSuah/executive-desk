<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EventStatus: string
{
    use HasOptions;

    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
