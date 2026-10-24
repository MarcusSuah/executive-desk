<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ReminderStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Unavailable = 'unavailable';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
    case Disabled = 'disabled';

    /**
     * Get the statuses that may still lead to a delivery.
     *
     * @return array<int, string>
     */
    public static function active(): array
    {
        return [self::Pending->value, self::Queued->value, self::Processing->value];
    }
}
