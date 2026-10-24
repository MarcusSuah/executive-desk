<?php

namespace App\Services\Reminders;

use App\Enums\PresentationStatus;
use App\Enums\ReminderKind;
use App\Enums\ReminderStatus;
use App\Models\CalendarEvent;
use App\Models\Presentation;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Support\NotificationCategories;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the persistent reminder rows for an event, task, or presentation in step with its schedule.
 *
 * Every desired reminder has a deterministic idempotency key derived from the record,
 * recipient, kind, channel, interval, and scheduled time. Rescheduling therefore produces
 * new keys: obsolete pending rows are cancelled and new rows inserted, while the unique
 * key prevents duplicates when the planner runs more than once.
 */
class ReminderPlanner
{
    /**
     * Synchronize reminders for any supported record.
     */
    public function sync(Model $remindable): void
    {
        match (true) {
            $remindable instanceof CalendarEvent => $this->syncEvent($remindable),
            $remindable instanceof Task => $this->syncTask($remindable),
            $remindable instanceof Presentation => $this->syncPresentation($remindable),
            default => null,
        };
    }

    /**
     * Synchronize reminders for a calendar event.
     */
    public function syncEvent(CalendarEvent $event): void
    {
        $plans = [];

        if (! $event->isCancelled() && ! $event->trashed()) {
            foreach ($this->activeUsers($event->recipientIds()) as $user) {
                $minutes = $event->reminder_minutes ?? $user->preferences()->event_reminder_minutes;

                $plans = [...$plans, ...$this->plansFor($user, ReminderKind::EventStart, $event->starts_at, $minutes)];
            }
        }

        $this->apply($event, $plans);
    }

    /**
     * Synchronize deadline reminders for a task.
     */
    public function syncTask(Task $task): void
    {
        $plans = [];

        if ($task->isOpen() && ! $task->trashed() && $task->due_at !== null && $task->assigned_to !== null) {
            foreach ($this->activeUsers([$task->assigned_to]) as $user) {
                $minutes = $user->preferences()->task_reminder_minutes;

                $plans = [...$plans, ...$this->plansFor($user, ReminderKind::TaskDue, $task->due_at, $minutes)];
            }
        }

        $this->apply($task, $plans);
    }

    /**
     * Synchronize preparation and review deadline reminders for a presentation.
     */
    public function syncPresentation(Presentation $presentation): void
    {
        $plans = [];

        if ($presentation->isActive() && ! $presentation->trashed()) {
            foreach ($this->activeUsers($presentation->recipientIds()) as $user) {
                $minutes = $user->preferences()->presentation_reminder_minutes;

                if ($presentation->preparation_deadline_at !== null) {
                    $plans = [...$plans, ...$this->plansFor($user, ReminderKind::PresentationPreparation, $presentation->preparation_deadline_at, $minutes)];
                }

                if ($presentation->review_deadline_at !== null) {
                    $plans = [...$plans, ...$this->plansFor($user, ReminderKind::PresentationReview, $presentation->review_deadline_at, $minutes)];
                }
            }
        }

        $this->apply($presentation, $plans);
    }

    /**
     * Re-plan every upcoming reminder that involves the user, e.g. after their preferences change.
     */
    public function syncForUser(User $user): void
    {
        CalendarEvent::query()
            ->active()
            ->where('starts_at', '>', now())
            ->where(fn ($query) => $query->where('owner_id', $user->id)
                ->orWhereHas('attendees', fn ($query) => $query->where('user_id', $user->id)))
            ->each(fn (CalendarEvent $event) => $this->syncEvent($event));

        Task::query()
            ->open()
            ->where('assigned_to', $user->id)
            ->where('due_at', '>', now())
            ->each(fn (Task $task) => $this->syncTask($task));

        Presentation::query()
            ->whereIn('status', PresentationStatus::active())
            ->where(fn ($query) => $query->where('owner_id', $user->id)->orWhere('presenter_id', $user->id))
            ->each(fn (Presentation $presentation) => $this->syncPresentation($presentation));
    }

    /**
     * Cancel every reminder that has not been delivered yet.
     */
    public function cancelAll(Model $remindable, string $reason): int
    {
        return Reminder::query()
            ->whereMorphedTo('remindable', $remindable)
            ->whereIn('status', [ReminderStatus::Pending->value, ReminderStatus::Disabled->value])
            ->update([
                'status' => ReminderStatus::Cancelled->value,
                'status_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    /**
     * Get the expected delivery time of a reminder given the current state of its record,
     * or null when the reminder is no longer relevant.
     */
    public function expectedScheduleFor(Reminder $reminder): ?CarbonImmutable
    {
        $remindable = $reminder->remindable;

        $target = match (true) {
            $remindable instanceof CalendarEvent => ! $remindable->isCancelled() && ! $remindable->trashed()
                ? $remindable->starts_at
                : null,
            $remindable instanceof Task => $remindable->isOpen() && ! $remindable->trashed() && $remindable->assigned_to === $reminder->user_id
                ? $remindable->due_at
                : null,
            $remindable instanceof Presentation => $remindable->isActive() && ! $remindable->trashed()
                ? ($reminder->kind === ReminderKind::PresentationReview ? $remindable->review_deadline_at : $remindable->preparation_deadline_at)
                : null,
            default => null,
        };

        return $target?->subMinutes($reminder->minutes_before);
    }

    /**
     * Build the desired reminder rows for one recipient and deadline.
     *
     * @param  array<int, int>  $minutes
     * @return array<int, array<string, mixed>>
     */
    private function plansFor(User $user, ReminderKind $kind, CarbonImmutable $target, array $minutes): array
    {
        $plans = [];
        $now = now();
        $channels = $user->enabledChannels(NotificationCategories::forReminder($kind));

        foreach (array_unique(array_map('intval', $minutes)) as $minutesBefore) {
            $scheduledFor = $target->subMinutes($minutesBefore);

            if ($minutesBefore <= 0 || $scheduledFor->lessThanOrEqualTo($now)) {
                continue;
            }

            foreach ($channels as $channel) {
                $plans[] = [
                    'user_id' => $user->id,
                    'kind' => $kind->value,
                    'channel' => $channel->value,
                    'minutes_before' => $minutesBefore,
                    'scheduled_for' => $scheduledFor,
                ];
            }
        }

        return $plans;
    }

    /**
     * Cancel obsolete pending reminders and insert missing ones in a single transaction.
     *
     * @param  array<int, array<string, mixed>>  $plans
     */
    private function apply(Model $remindable, array $plans): void
    {
        $rows = [];

        foreach ($plans as $plan) {
            $key = $this->idempotencyKey($remindable, $plan);

            $rows[$key] = [
                ...$plan,
                'remindable_type' => $remindable->getMorphClass(),
                'remindable_id' => $remindable->getKey(),
                'scheduled_for' => $plan['scheduled_for']->utc()->format('Y-m-d H:i:s'),
                'status' => ReminderStatus::Pending->value,
                'idempotency_key' => $key,
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($remindable, $rows): void {
            Reminder::query()
                ->whereMorphedTo('remindable', $remindable)
                ->whereIn('status', [ReminderStatus::Pending->value, ReminderStatus::Disabled->value])
                ->when($rows !== [], fn ($query) => $query->whereNotIn('idempotency_key', array_keys($rows)))
                ->update([
                    'status' => ReminderStatus::Cancelled->value,
                    'status_reason' => 'Schedule changed',
                    'updated_at' => now(),
                ]);

            if ($rows !== []) {
                Reminder::query()->insertOrIgnore(array_values($rows));

                // A schedule that moves away and back again reuses earlier keys; revive those rows.
                Reminder::query()
                    ->whereIn('idempotency_key', array_keys($rows))
                    ->where('status', ReminderStatus::Cancelled->value)
                    ->update([
                        'status' => ReminderStatus::Pending->value,
                        'status_reason' => null,
                        'attempts' => 0,
                        'reserved_at' => null,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * Build the deterministic idempotency key for a reminder.
     *
     * @param  array<string, mixed>  $plan
     */
    private function idempotencyKey(Model $remindable, array $plan): string
    {
        return hash('sha256', implode('|', [
            $remindable->getMorphClass(),
            $remindable->getKey(),
            $plan['user_id'],
            $plan['kind'],
            $plan['channel'],
            $plan['minutes_before'],
            $plan['scheduled_for']->utc()->format('Y-m-d\TH:i:s\Z'),
        ]));
    }

    /**
     * Load the active users among the given IDs.
     *
     * @param  array<int, int|null>  $userIds
     * @return iterable<int, User>
     */
    private function activeUsers(array $userIds): iterable
    {
        $userIds = array_values(array_filter($userIds));

        if ($userIds === []) {
            return [];
        }

        return User::query()->active()->whereIn('id', $userIds)->with('notificationPreference')->get();
    }
}
