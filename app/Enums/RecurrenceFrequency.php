<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Carbon\CarbonImmutable;

enum RecurrenceFrequency: string
{
    use HasOptions;

    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Get the occurrence date that follows the given date.
     */
    public function next(CarbonImmutable $date, int $interval): CarbonImmutable
    {
        $interval = max(1, $interval);

        return match ($this) {
            self::Daily => $date->addDays($interval),
            self::Weekdays => $date->addWeekday(),
            self::Weekly => $date->addWeeks($interval),
            self::Monthly => $date->addMonthsNoOverflow($interval),
        };
    }
}
