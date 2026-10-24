<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DeliveryStatus: string
{
    use HasOptions;

    /** The provider accepted the message; this does not prove it reached the device. */
    case Sent = 'sent';
    case Failed = 'failed';
    case Unavailable = 'unavailable';
    case Skipped = 'skipped';
}
