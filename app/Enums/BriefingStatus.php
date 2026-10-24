<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BriefingStatus: string
{
    use HasOptions;

    case Generated = 'generated';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Failed = 'failed';
}
