/**
 * Date helpers that always format in an explicit IANA time zone (the signed-in
 * user's or an event's), never the browser's local zone.
 */

export type ZonedParts = {
    year: number;
    month: number;
    day: number;
    hour: number;
    minute: number;
    weekday: number;
};

const partsFormatters = new Map<string, Intl.DateTimeFormat>();

function partsFormatter(timeZone: string): Intl.DateTimeFormat {
    let formatter = partsFormatters.get(timeZone);

    if (!formatter) {
        formatter = new Intl.DateTimeFormat('en-US', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            weekday: 'short',
            hourCycle: 'h23',
        });
        partsFormatters.set(timeZone, formatter);
    }

    return formatter;
}

const WEEKDAYS: Record<string, number> = {
    Sun: 0,
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
};

/**
 * Get the wall-clock components of an instant in a time zone.
 */
export function zonedParts(iso: string, timeZone: string): ZonedParts {
    const parts = partsFormatter(timeZone).formatToParts(new Date(iso));
    const get = (type: Intl.DateTimeFormatPartTypes): string =>
        parts.find((part) => part.type === type)?.value ?? '0';

    return {
        year: Number(get('year')),
        month: Number(get('month')),
        day: Number(get('day')),
        hour: Number(get('hour')) % 24,
        minute: Number(get('minute')),
        weekday: WEEKDAYS[get('weekday')] ?? 0,
    };
}

const pad = (value: number): string => String(value).padStart(2, '0');

/**
 * Get the local calendar date ("YYYY-MM-DD") of an instant in a time zone.
 */
export function dateKey(iso: string, timeZone: string): string {
    const parts = zonedParts(iso, timeZone);

    return `${parts.year}-${pad(parts.month)}-${pad(parts.day)}`;
}

/**
 * Get the minutes since local midnight of an instant in a time zone.
 */
export function minutesIntoDay(iso: string, timeZone: string): number {
    const parts = zonedParts(iso, timeZone);

    return parts.hour * 60 + parts.minute;
}

/**
 * Format an instant as a value for a datetime-local input in a time zone.
 */
export function toLocalInput(
    iso: string | null | undefined,
    timeZone: string,
): string {
    if (!iso) {
        return '';
    }

    const parts = zonedParts(iso, timeZone);

    return `${parts.year}-${pad(parts.month)}-${pad(parts.day)}T${pad(parts.hour)}:${pad(parts.minute)}`;
}

/**
 * Format an instant, e.g. "Mon, Oct 26, 2026, 10:00 AM".
 */
export function formatDateTime(
    iso: string | null | undefined,
    timeZone: string,
    options: Intl.DateTimeFormatOptions = {},
): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        timeZone,
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        ...options,
    }).format(new Date(iso));
}

/**
 * Format the time of an instant, e.g. "10:00 AM".
 */
export function formatTime(iso: string, timeZone: string): string {
    return new Intl.DateTimeFormat(undefined, {
        timeZone,
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
}

/**
 * Format the date of an instant, e.g. "Mon, Oct 26".
 */
export function formatDate(
    iso: string | null | undefined,
    timeZone: string,
    options: Intl.DateTimeFormatOptions = {},
): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        timeZone,
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        ...options,
    }).format(new Date(iso));
}

/**
 * Format a calendar date key ("YYYY-MM-DD"), which has no time zone of its own.
 */
export function formatDateKey(
    key: string,
    options: Intl.DateTimeFormatOptions = {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    },
): string {
    return new Intl.DateTimeFormat(undefined, {
        timeZone: 'UTC',
        ...options,
    }).format(new Date(`${key}T12:00:00Z`));
}

/**
 * Format a time range in a time zone, e.g. "10:00 AM – 11:00 AM".
 */
export function formatTimeRange(
    startIso: string,
    endIso: string,
    timeZone: string,
): string {
    const sameDay = dateKey(startIso, timeZone) === dateKey(endIso, timeZone);

    return sameDay
        ? `${formatTime(startIso, timeZone)} – ${formatTime(endIso, timeZone)}`
        : `${formatDateTime(startIso, timeZone)} – ${formatDateTime(endIso, timeZone)}`;
}

/**
 * Get the short name of a time zone at an instant, e.g. "PHT" or "GMT+8".
 */
export function zoneAbbreviation(iso: string, timeZone: string): string {
    return (
        new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'short' })
            .formatToParts(new Date(iso))
            .find((part) => part.type === 'timeZoneName')?.value ?? timeZone
    );
}

/**
 * Describe the time until an instant, e.g. "in 2h 14m" or "started 5m ago".
 */
export function countdown(targetIso: string, nowMs: number): string {
    const diffMinutes = Math.round(
        (new Date(targetIso).getTime() - nowMs) / 60000,
    );
    const absolute = Math.abs(diffMinutes);
    const days = Math.floor(absolute / 1440);
    const hours = Math.floor((absolute % 1440) / 60);
    const minutes = absolute % 60;

    const text =
        days > 0
            ? `${days}d ${hours}h`
            : hours > 0
              ? `${hours}h ${minutes}m`
              : `${minutes}m`;

    if (diffMinutes === 0) {
        return 'now';
    }

    return diffMinutes > 0 ? `in ${text}` : `started ${text} ago`;
}

/**
 * Describe a reminder interval, e.g. "15 minutes", "1 hour", "2 days".
 */
export function describeMinutes(minutes: number): string {
    if (minutes % 1440 === 0) {
        const days = minutes / 1440;

        return `${days} day${days === 1 ? '' : 's'}`;
    }

    if (minutes % 60 === 0) {
        const hours = minutes / 60;

        return `${hours} hour${hours === 1 ? '' : 's'}`;
    }

    return `${minutes} minute${minutes === 1 ? '' : 's'}`;
}

/**
 * Add days to a calendar date key.
 */
export function addDays(key: string, days: number): string {
    const date = new Date(`${key}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}
