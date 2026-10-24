<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AttendeeResponse: string
{
    use HasOptions;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Tentative = 'tentative';
    case Declined = 'declined';
}
