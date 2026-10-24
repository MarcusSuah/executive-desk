<?php

namespace App\Services\Calendar;

use App\Enums\AttendeeResponse;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Reminders\ReminderPlanner;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates, reschedules, and cancels calendar events.
 *
 * Each change runs in a transaction that locks the owner's user row, which serializes
 * schedule changes per calendar so two concurrent bookings cannot both pass the conflict
 * check. Updates also use an optimistic version number so a stale form cannot overwrite
 * a newer change.
 */
class EventScheduler
{
    /**
     * Attributes whose change counts as a reschedule.
     *
     * @var array<int, string>
     */
    private const SCHEDULE_FIELDS = ['starts_at', 'ends_at', 'timezone', 'all_day'];

    /**
     * Attributes whose change attendees are told about.
     *
     * @var array<int, string>
     */
    private const NOTIFIABLE_FIELDS = ['starts_at', 'ends_at', 'location', 'meeting_url'];

    public function __construct(
        private ConflictDetector $conflicts,
        private ReminderPlanner $reminders,
        private AuditLogger $audit,
        private ScheduleChangeNotifier $notifier,
    ) {}

    /**
     * Create an event.
     *
     * @param  array<string, mixed>  $attributes  Validated attributes with UTC starts_at/ends_at.
     * @param  array<int, array{user_id: int|null, name: string, email: string|null, role: string}>  $attendees
     * @param  array<int, string>  $checklist
     *
     * @throws ValidationException when the event conflicts and the conflict was not acknowledged.
     */
    public function create(array $attributes, array $attendees, array $checklist, User $actor, bool $acknowledgeConflicts): CalendarEvent
    {
        return DB::transaction(function () use ($attributes, $attendees, $checklist, $actor, $acknowledgeConflicts): CalendarEvent {
            $this->lockCalendar($attributes['owner_id']);

            $hasConflicts = $this->guardConflicts($attributes, null, $acknowledgeConflicts, $actor);

            $event = new CalendarEvent($attributes);
            $event->created_by = $actor->id;
            $event->conflict_acknowledged = $hasConflicts;
            $event->save();

            $this->syncAttendees($event, $attendees);

            foreach (array_values($checklist) as $position => $title) {
                $event->checklistItems()->create(['title' => $title, 'position' => $position]);
            }

            $this->audit->log('event.created', $event, [], $event->only([
                'title', 'owner_id', 'type', 'starts_at', 'ends_at', 'timezone', 'location', 'priority',
            ]) + ['conflict_acknowledged' => $hasConflicts], $actor);

            $this->reminders->syncEvent($event->load('attendees'));

            return $event;
        });
    }

    /**
     * Update or reschedule an event.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{user_id: int|null, name: string, email: string|null, role: string}>  $attendees
     *
     * @throws ValidationException on a version mismatch or an unacknowledged conflict.
     */
    public function update(CalendarEvent $event, array $attributes, array $attendees, User $actor, int $expectedVersion, bool $acknowledgeConflicts): CalendarEvent
    {
        return DB::transaction(function () use ($event, $attributes, $attendees, $actor, $expectedVersion, $acknowledgeConflicts): CalendarEvent {
            $ownerIds = array_unique([$event->owner_id, $attributes['owner_id']]);
            sort($ownerIds);

            foreach ($ownerIds as $ownerId) {
                $this->lockCalendar($ownerId);
            }

            $current = CalendarEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($current->version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'version' => 'This appointment was changed by someone else while you were editing. Reload the page to see the latest version.',
                ]);
            }

            if ($current->isCancelled()) {
                throw ValidationException::withMessages(['status' => 'Cancelled appointments cannot be edited.']);
            }

            $hasConflicts = $this->guardConflicts($attributes, $current->id, $acknowledgeConflicts, $actor);

            $previous = ['starts_at' => $current->starts_at, 'ends_at' => $current->ends_at, 'location' => $current->location];

            $current->fill($attributes);
            $current->conflict_acknowledged = $hasConflicts;
            $current->version = $current->version + 1;
            $current->save();

            $this->syncAttendees($current, $attendees);

            $changed = array_keys($current->getChanges());
            $isReschedule = array_intersect(self::SCHEDULE_FIELDS, $changed) !== [];

            $this->audit->logChanges($isReschedule ? 'event.rescheduled' : 'event.updated', $current, $actor);

            $this->reminders->syncEvent($current->load('attendees'));

            if (array_intersect(self::NOTIFIABLE_FIELDS, $changed) !== []) {
                $this->notifier->notify($current, $actor, $isReschedule ? 'rescheduled' : 'updated', $previous);
            }

            return $current;
        });
    }

    /**
     * Cancel an event and its pending reminders.
     */
    public function cancel(CalendarEvent $event, User $actor, ?string $reason): CalendarEvent
    {
        return DB::transaction(function () use ($event, $actor, $reason): CalendarEvent {
            $this->lockCalendar($event->owner_id);

            $current = CalendarEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($current->isCancelled()) {
                return $current;
            }

            $current->forceFill([
                'status' => EventStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'version' => $current->version + 1,
            ])->save();

            $this->audit->log('event.cancelled', $current, ['status' => EventStatus::Scheduled->value], [
                'status' => EventStatus::Cancelled->value,
                'cancellation_reason' => $reason,
            ], $actor);

            $this->reminders->cancelAll($current, 'Appointment cancelled');

            if ($current->ends_at->isFuture()) {
                $this->notifier->notify($current, $actor, 'cancelled', [
                    'starts_at' => $current->starts_at,
                    'ends_at' => $current->ends_at,
                    'location' => $current->location,
                ]);
            }

            return $current;
        });
    }

    /**
     * Soft delete an event.
     */
    public function delete(CalendarEvent $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $this->reminders->cancelAll($event, 'Appointment deleted');
            $event->delete();

            $this->audit->log('event.deleted', $event, $event->only(['title', 'starts_at', 'ends_at']), [], $actor);
        });
    }

    /**
     * Mark a past event as completed.
     */
    public function complete(CalendarEvent $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $event->forceFill(['status' => EventStatus::Completed, 'version' => $event->version + 1])->save();

            $this->audit->log('event.completed', $event, ['status' => EventStatus::Scheduled->value], ['status' => EventStatus::Completed->value], $actor);
        });
    }

    /**
     * Lock the owner's row so schedule changes on one calendar run one at a time.
     */
    private function lockCalendar(int $ownerId): void
    {
        User::query()->whereKey($ownerId)->lockForUpdate()->first(['id']);
    }

    /**
     * Throw when the interval overlaps other events and the user has not acknowledged it.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function guardConflicts(array $attributes, ?int $ignoreEventId, bool $acknowledged, User $actor): bool
    {
        /** @var CarbonImmutable $startsAt */
        $startsAt = $attributes['starts_at'];
        /** @var CarbonImmutable $endsAt */
        $endsAt = $attributes['ends_at'];

        $conflicts = $this->conflicts->conflictsFor(
            $attributes['owner_id'],
            $startsAt,
            $endsAt,
            $attributes['type'] instanceof EventType ? $attributes['type'] : EventType::from($attributes['type']),
            (bool) ($attributes['all_day'] ?? false),
            $ignoreEventId,
        );

        if ($conflicts->isEmpty()) {
            return false;
        }

        if (! $acknowledged) {
            $list = $conflicts
                ->map(fn (CalendarEvent $conflict) => '“'.$conflict->title.'” ('.DisplayTime::range($conflict->starts_at, $conflict->ends_at, $actor->timezone).')')
                ->implode('; ');

            throw ValidationException::withMessages([
                'conflicts' => "This time overlaps with {$list}. Confirm to save it anyway.",
            ]);
        }

        return true;
    }

    /**
     * Replace the attendee list while preserving existing responses.
     *
     * @param  array<int, array{user_id: int|null, name: string, email: string|null, role: string}>  $attendees
     */
    private function syncAttendees(CalendarEvent $event, array $attendees): void
    {
        $existing = $event->attendees()->get();

        $responseFor = function (array $attendee) use ($existing): string {
            $match = $existing->first(fn ($current) => ($attendee['user_id'] !== null && $current->user_id === $attendee['user_id'])
                || ($attendee['user_id'] === null && $attendee['email'] !== null && $current->email === $attendee['email']));

            return $match?->response_status->value ?? AttendeeResponse::Pending->value;
        };

        $rows = collect($attendees)
            ->unique(fn (array $attendee) => $attendee['user_id'] ?? 'email:'.Arr::get($attendee, 'email', $attendee['name']))
            ->map(fn (array $attendee) => [...$attendee, 'response_status' => $responseFor($attendee)]);

        $event->attendees()->delete();
        $event->attendees()->createMany($rows->values()->all());
        $event->unsetRelation('attendees');
    }
}
