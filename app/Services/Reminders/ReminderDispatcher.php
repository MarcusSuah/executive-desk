<?php

namespace App\Services\Reminders;

use App\Enums\ReminderStatus;
use App\Jobs\SendReminder;
use App\Models\Reminder;
use Illuminate\Validation\ValidationException;

/**
 * Finds due reminders and hands them to the queue. Safe to run concurrently: each
 * reminder is claimed with a conditional update before its job is dispatched.
 */
class ReminderDispatcher
{
    public function __construct(private ReminderPlanner $planner) {}

    /**
     * Run one dispatch cycle.
     *
     * @return array{recovered: int, interrupted: int, missed: int, dispatched: int}
     */
    public function run(): array
    {
        [$recovered, $interrupted] = $this->recoverStale();

        return [
            'recovered' => $recovered,
            'interrupted' => $interrupted,
            'missed' => $this->skipMissed(),
            'dispatched' => $this->dispatchDue(),
        ];
    }

    /**
     * Recover reminders stuck in an intermediate state.
     *
     * A queued reminder whose job was lost is dispatched again. A reminder stuck while
     * processing may or may not have reached the provider, so it is failed rather than
     * resent, to avoid a duplicate notification.
     *
     * @return array{0: int, 1: int}
     */
    public function recoverStale(): array
    {
        $config = config('executive-desk.reminders');
        $recovered = 0;

        $staleQueued = Reminder::query()
            ->where('status', ReminderStatus::Queued->value)
            ->where('reserved_at', '<', now()->subMinutes($config['stale_queued_minutes']))
            ->limit($config['dispatch_batch_size'])
            ->get(['id', 'attempts', 'reserved_at']);

        foreach ($staleQueued as $reminder) {
            if ($reminder->attempts >= $config['max_attempts']) {
                Reminder::query()->whereKey($reminder->id)->where('status', ReminderStatus::Queued->value)->update([
                    'status' => ReminderStatus::Failed->value,
                    'last_error' => 'Retry limit reached.',
                    'reserved_at' => null,
                    'updated_at' => now(),
                ]);

                continue;
            }

            $claimed = Reminder::query()
                ->whereKey($reminder->id)
                ->where('status', ReminderStatus::Queued->value)
                ->where('reserved_at', $reminder->reserved_at)
                ->update(['reserved_at' => now(), 'updated_at' => now()]);

            if ($claimed === 1) {
                SendReminder::dispatch($reminder->id);
                $recovered++;
            }
        }

        $interrupted = Reminder::query()
            ->where('status', ReminderStatus::Processing->value)
            ->where('reserved_at', '<', now()->subMinutes($config['stale_processing_minutes']))
            ->update([
                'status' => ReminderStatus::Failed->value,
                'last_error' => 'Delivery was interrupted and its outcome is unknown; it was not resent to avoid a duplicate.',
                'reserved_at' => null,
                'updated_at' => now(),
            ]);

        return [$recovered, $interrupted];
    }

    /**
     * Skip pending reminders whose delivery window has passed.
     */
    public function skipMissed(): int
    {
        return Reminder::query()
            ->where('status', ReminderStatus::Pending->value)
            ->where('scheduled_for', '<', now()->subMinutes(config('executive-desk.reminders.grace_minutes')))
            ->update([
                'status' => ReminderStatus::Skipped->value,
                'status_reason' => 'Missed the delivery window.',
                'updated_at' => now(),
            ]);
    }

    /**
     * Claim and dispatch due reminders.
     */
    public function dispatchDue(): int
    {
        $dispatched = 0;

        $dueIds = Reminder::query()
            ->where('status', ReminderStatus::Pending->value)
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit(config('executive-desk.reminders.dispatch_batch_size'))
            ->pluck('id');

        foreach ($dueIds as $id) {
            $claimed = Reminder::query()
                ->whereKey($id)
                ->where('status', ReminderStatus::Pending->value)
                ->update([
                    'status' => ReminderStatus::Queued->value,
                    'reserved_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($claimed === 1) {
                SendReminder::dispatch($id);
                $dispatched++;
            }
        }

        return $dispatched;
    }

    /**
     * Manually retry a failed reminder whose record is still upcoming.
     *
     * @throws ValidationException
     */
    public function retry(Reminder $reminder): void
    {
        $expected = $this->planner->expectedScheduleFor($reminder);

        if ($expected === null || ! $expected->equalTo($reminder->scheduled_for)) {
            throw ValidationException::withMessages([
                'reminder' => 'This reminder is no longer relevant because its appointment, task, or presentation changed.',
            ]);
        }

        if ($expected->addMinutes($reminder->minutes_before)->isPast()) {
            throw ValidationException::withMessages([
                'reminder' => 'This reminder can no longer be retried because its deadline has passed.',
            ]);
        }

        $claimed = Reminder::query()
            ->whereKey($reminder->id)
            ->where('status', ReminderStatus::Failed->value)
            ->update([
                'status' => ReminderStatus::Queued->value,
                'attempts' => 0,
                'reserved_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);

        if ($claimed === 1) {
            SendReminder::dispatch($reminder->id);
        }
    }
}
