<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Converts between wall-clock times in IANA time zones and UTC storage.
 */
class Timezones
{
    /**
     * The format used for local date-time values exchanged with forms.
     */
    public const LOCAL_FORMAT = 'Y-m-d\TH:i';

    /**
     * Get all valid IANA time-zone identifiers.
     *
     * @return array<int, string>
     */
    public static function identifiers(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * Convert a wall-clock time in the given time zone to UTC.
     *
     * Throws when the wall-clock time does not exist in that zone (for example,
     * inside a daylight-saving "spring forward" gap).
     */
    public static function localToUtc(string $localDateTime, string $timezone): CarbonImmutable
    {
        $local = CarbonImmutable::createFromFormat(self::LOCAL_FORMAT, $localDateTime, $timezone);

        if ($local === null || $local->format(self::LOCAL_FORMAT) !== $localDateTime) {
            throw new InvalidArgumentException("The time {$localDateTime} does not exist in {$timezone}.");
        }

        return $local->utc();
    }

    /**
     * Determine whether a wall-clock time exists in the given time zone.
     */
    public static function localTimeExists(string $localDateTime, string $timezone): bool
    {
        try {
            static::localToUtc($localDateTime, $timezone);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Format a UTC instant as a wall-clock time in the given time zone.
     */
    public static function utcToLocal(?CarbonImmutable $instant, string $timezone): ?string
    {
        return $instant?->setTimezone($timezone)->format(self::LOCAL_FORMAT);
    }
}
