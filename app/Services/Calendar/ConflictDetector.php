<?php

namespace App\Services\Calendar;

use App\Enums\EventType;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class ConflictDetector
{
    /**
     * Event types that mark a point in time rather than blocking the calendar.
     *
     * @var array<int, EventType>
     */
    private const NON_BLOCKING_TYPES = [EventType::Deadline];

    /**
     * Find scheduled events on the owner's calendar that overlap the given UTC interval.
     *
     * Intervals are half-open: an event ending at 10:00 does not conflict with one starting at 10:00.
     *
     * @return Collection<int, CalendarEvent>
     */
    public function conflictsFor(
        int $ownerId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        EventType $type,
        bool $allDay = false,
        ?int $ignoreEventId = null,
    ): Collection {
        if ($allDay || in_array($type, self::NON_BLOCKING_TYPES, true)) {
            return new Collection;
        }

        return CalendarEvent::query()
            ->where('owner_id', $ownerId)
            ->active()
            ->where('all_day', false)
            ->whereNotIn('type', array_map(fn (EventType $type) => $type->value, self::NON_BLOCKING_TYPES))
            ->overlapping($startsAt, $endsAt)
            ->when($ignoreEventId !== null, fn ($query) => $query->whereKeyNot($ignoreEventId))
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at', 'ends_at', 'timezone']);
    }
}
