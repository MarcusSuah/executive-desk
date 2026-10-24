import { addDays, dateKey, minutesIntoDay } from '@/lib/datetime';
import type { CalendarEvent } from '@/types';

/**
 * Height in pixels of one hour in the day and week time grids.
 */
export const HOUR_HEIGHT = 48;

/**
 * Get the local dates ("YYYY-MM-DD") an event covers in the viewer's time zone.
 */
export function coveredDays(event: CalendarEvent, timezone: string): string[] {
    const first = dateKey(event.starts_at, timezone);
    // An event ending exactly at midnight does not cover the next day.
    const endIso = new Date(
        new Date(event.ends_at).getTime() - 60_000,
    ).toISOString();
    const last =
        dateKey(endIso, timezone) < first ? first : dateKey(endIso, timezone);
    const days: string[] = [];

    for (
        let day = first;
        day <= last && days.length < 62;
        day = addDays(day, 1)
    ) {
        days.push(day);
    }

    return days;
}

export type PositionedEvent = {
    event: CalendarEvent;
    top: number;
    height: number;
    lane: number;
    lanes: number;
};

/**
 * Position timed events within one day, placing overlapping events in side-by-side lanes.
 */
export function layoutDay(
    day: string,
    events: CalendarEvent[],
    timezone: string,
): PositionedEvent[] {
    const timed = events
        .filter((event) => !event.all_day)
        .map((event) => {
            const start =
                dateKey(event.starts_at, timezone) < day
                    ? 0
                    : minutesIntoDay(event.starts_at, timezone);
            const endsLater = dateKey(event.ends_at, timezone) > day;
            const end = endsLater
                ? 1440
                : Math.max(start + 15, minutesIntoDay(event.ends_at, timezone));

            return { event, start, end };
        })
        .sort((a, b) => a.start - b.start || b.end - a.end);

    const positioned: PositionedEvent[] = [];
    let cluster: ((typeof timed)[number] & { lane: number })[] = [];
    let clusterEnd = -1;

    const flush = () => {
        const lanes = Math.max(1, ...cluster.map((item) => item.lane + 1));

        for (const item of cluster) {
            positioned.push({
                event: item.event,
                top: (item.start / 60) * HOUR_HEIGHT,
                height: Math.max(
                    20,
                    ((item.end - item.start) / 60) * HOUR_HEIGHT - 2,
                ),
                lane: item.lane,
                lanes,
            });
        }

        cluster = [];
        clusterEnd = -1;
    };

    for (const item of timed) {
        if (item.start >= clusterEnd && cluster.length > 0) {
            flush();
        }

        const usedLanes = cluster
            .filter((other) => other.end > item.start)
            .map((other) => other.lane);
        let lane = 0;
        while (usedLanes.includes(lane)) {
            lane++;
        }

        cluster.push({ ...item, lane });
        clusterEnd = Math.max(clusterEnd, item.end);
    }

    if (cluster.length > 0) {
        flush();
    }

    return positioned;
}
