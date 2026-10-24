import { describe, expect, it } from 'vite-plus/test';
import { coveredDays, HOUR_HEIGHT, layoutDay } from '@/lib/calendar-layout';
import type { CalendarEvent } from '@/types';

function event(
    id: number,
    startsAt: string,
    endsAt: string,
    allDay = false,
): CalendarEvent {
    return {
        id,
        title: `Event ${id}`,
        description: null,
        type: 'internal_meeting',
        type_label: 'Internal Meeting',
        category: null,
        priority: 'normal',
        status: 'scheduled',
        starts_at: startsAt,
        ends_at: endsAt,
        timezone: 'UTC',
        all_day: allDay,
        location: null,
        meeting_url: null,
        agenda: null,
        notes: null,
        preparation_notes: null,
        reminder_minutes: null,
        conflict_acknowledged: false,
        cancelled_at: null,
        cancellation_reason: null,
        version: 1,
        created_at: null,
        updated_at: null,
    };
}

describe('coveredDays', () => {
    it('places an event on the local day of the viewer time zone', () => {
        expect(
            coveredDays(
                event(1, '2026-10-25T23:00:00Z', '2026-10-26T00:00:00Z'),
                'Asia/Manila',
            ),
        ).toEqual(['2026-10-26']);
    });

    it('does not spill an event that ends exactly at midnight onto the next day', () => {
        expect(
            coveredDays(
                event(1, '2026-10-26T22:00:00Z', '2026-10-27T00:00:00Z'),
                'UTC',
            ),
        ).toEqual(['2026-10-26']);
    });

    it('covers every day of a multi-day event', () => {
        expect(
            coveredDays(
                event(1, '2026-10-26T09:00:00Z', '2026-10-28T10:00:00Z'),
                'UTC',
            ),
        ).toEqual(['2026-10-26', '2026-10-27', '2026-10-28']);
    });
});

describe('layoutDay', () => {
    it('positions an event by its local start time and duration', () => {
        const [positioned] = layoutDay(
            '2026-10-26',
            [event(1, '2026-10-26T01:00:00Z', '2026-10-26T02:30:00Z')],
            'Asia/Manila',
        );

        expect(positioned.top).toBe(9 * HOUR_HEIGHT);
        expect(positioned.height).toBe(1.5 * HOUR_HEIGHT - 2);
        expect(positioned.lanes).toBe(1);
    });

    it('places overlapping events in separate lanes', () => {
        const layout = layoutDay(
            '2026-10-26',
            [
                event(1, '2026-10-26T09:00:00Z', '2026-10-26T11:00:00Z'),
                event(2, '2026-10-26T10:00:00Z', '2026-10-26T10:30:00Z'),
                event(3, '2026-10-26T13:00:00Z', '2026-10-26T14:00:00Z'),
            ],
            'UTC',
        );

        const byId = Object.fromEntries(
            layout.map((item) => [item.event.id, item]),
        );

        expect(byId[1].lane).toBe(0);
        expect(byId[2].lane).toBe(1);
        expect(byId[1].lanes).toBe(2);
        expect(byId[3].lanes).toBe(1);
    });

    it('clips a multi-day event to the day being drawn and skips all-day events', () => {
        const layout = layoutDay(
            '2026-10-27',
            [
                event(1, '2026-10-26T20:00:00Z', '2026-10-27T03:00:00Z'),
                event(2, '2026-10-27T00:00:00Z', '2026-10-28T00:00:00Z', true),
            ],
            'UTC',
        );

        expect(layout).toHaveLength(1);
        expect(layout[0].top).toBe(0);
        expect(layout[0].height).toBe(3 * HOUR_HEIGHT - 2);
    });
});
