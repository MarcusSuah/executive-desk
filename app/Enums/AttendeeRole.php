<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AttendeeRole: string
{
    use HasOptions;

    case Organizer = 'organizer';
    case Required = 'required';
    case Optional = 'optional';
}
