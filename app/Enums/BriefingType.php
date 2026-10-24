<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BriefingType: string
{
    use HasOptions;

    case Daily = 'daily';
    case Weekly = 'weekly';
}
