<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Formats UTC instants for people, always in an explicit IANA time zone.
 */
class DisplayTime
{
    /**
     * Format an instant in the user's time zone, e.g. "Mon, Oct 26, 2026 10:00 AM PHT".
     */
    public static function forUser(?CarbonImmutable $instant, User $user): string
    {
        return static::inZone($instant, $user->timezone);
    }

    /**
     * Format an instant in the given time zone.
     */
    public static function inZone(?CarbonImmutable $instant, string $timezone): string
    {
        if ($instant === null) {
            return '—';
        }

        return $instant->setTimezone($timezone)->format('D, M j, Y g:i A T');
    }

    /**
     * Format a time range in the given time zone, collapsing the date when both ends share it.
     */
    public static function range(CarbonImmutable $start, CarbonImmutable $end, string $timezone): string
    {
        $localStart = $start->setTimezone($timezone);
        $localEnd = $end->setTimezone($timezone);

        if ($localStart->isSameDay($localEnd)) {
            return $localStart->format('D, M j, Y g:i A').' – '.$localEnd->format('g:i A T');
        }

        return $localStart->format('D, M j, Y g:i A').' – '.$localEnd->format('D, M j, Y g:i A T');
    }

    /**
     * Describe an interval in minutes, e.g. "15 minutes", "1 hour", "2 days".
     */
    public static function minutes(int $minutes): string
    {
        return match (true) {
            $minutes % 1440 === 0 => trans_choice('{1} :count day|[2,*] :count days', intdiv($minutes, 1440)),
            $minutes % 60 === 0 => trans_choice('{1} :count hour|[2,*] :count hours', intdiv($minutes, 60)),
            default => trans_choice('{1} :count minute|[2,*] :count minutes', $minutes),
        };
    }
}
