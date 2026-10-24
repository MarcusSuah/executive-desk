import { describe, expect, it } from 'vite-plus/test';
import {
    addDays,
    countdown,
    dateKey,
    describeMinutes,
    minutesIntoDay,
    toLocalInput,
} from '@/lib/datetime';

describe('time zone conversion', () => {
    it('formats an instant as wall-clock time in the given zone, not the browser zone', () => {
        expect(toLocalInput('2026-11-02T02:00:00Z', 'Asia/Manila')).toBe(
            '2026-11-02T10:00',
        );
        expect(toLocalInput('2026-11-02T02:00:00Z', 'America/New_York')).toBe(
            '2026-11-01T21:00',
        );
    });

    it('applies daylight-saving offsets on each side of the change', () => {
        // New York leaves daylight time at 06:00 UTC on 1 November 2026.
        expect(toLocalInput('2026-10-31T13:00:00Z', 'America/New_York')).toBe(
            '2026-10-31T09:00',
        );
        expect(toLocalInput('2026-11-02T14:00:00Z', 'America/New_York')).toBe(
            '2026-11-02T09:00',
        );
    });

    it('returns the local calendar date of an instant', () => {
        expect(dateKey('2026-10-25T23:30:00Z', 'Asia/Manila')).toBe(
            '2026-10-26',
        );
        expect(dateKey('2026-10-25T23:30:00Z', 'UTC')).toBe('2026-10-25');
    });

    it('counts minutes since local midnight without returning hour 24', () => {
        expect(minutesIntoDay('2026-10-25T16:00:00Z', 'Asia/Manila')).toBe(0);
        expect(minutesIntoDay('2026-10-26T01:30:00Z', 'Asia/Manila')).toBe(570);
    });

    it('returns an empty input value for a missing time', () => {
        expect(toLocalInput(null, 'UTC')).toBe('');
    });
});

describe('helpers', () => {
    it('adds days across month boundaries', () => {
        expect(addDays('2026-10-31', 1)).toBe('2026-11-01');
        expect(addDays('2026-03-01', -1)).toBe('2026-02-28');
    });

    it('describes reminder intervals', () => {
        expect(describeMinutes(5)).toBe('5 minutes');
        expect(describeMinutes(60)).toBe('1 hour');
        expect(describeMinutes(2880)).toBe('2 days');
    });

    it('describes time until and since a meeting', () => {
        const now = Date.parse('2026-10-26T08:00:00Z');

        expect(countdown('2026-10-26T10:14:00Z', now)).toBe('in 2h 14m');
        expect(countdown('2026-10-26T07:55:00Z', now)).toBe('started 5m ago');
    });
});
