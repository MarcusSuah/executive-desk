<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\AuditLogger;
use App\Services\Reminders\ReminderPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Generates upcoming occurrences of recurring tasks.
 *
 * Occurrences are keyed by (recurrence_parent_id, occurrence_date) with a unique index,
 * so concurrent or repeated runs cannot create the same occurrence twice. Due times keep
 * their local wall-clock time in the task's time zone across daylight-saving changes.
 */
class RecurringTaskGenerator
{
    public function __construct(
        private ReminderPlanner $reminders,
        private AuditLogger $audit,
    ) {}

    /**
     * Generate occurrences for every recurring task.
     */
    public function generateAll(?CarbonImmutable $now = null): int
    {
        $created = 0;

        Task::query()
            ->whereNotNull('recurrence_frequency')
            ->whereNull('recurrence_parent_id')
            ->where('status', '!=', TaskStatus::Cancelled->value)
            ->with('checklistItems')
            ->each(function (Task $task) use (&$created, $now): void {
                $created += $this->generateFor($task, $now);
            });

        return $created;
    }

    /**
     * Generate the occurrences of one recurring task up to the horizon.
     */
    public function generateFor(Task $root, ?CarbonImmutable $now = null): int
    {
        if (! $root->isRecurring() || $root->occurrence_date === null) {
            return 0;
        }

        $now ??= now();
        $timezone = $root->timezone;
        $horizon = $now->setTimezone($timezone)->startOfDay()->addDays(config('executive-desk.recurring_tasks.horizon_days'));
        $endsOn = $root->recurrence_ends_on?->setTimezone($timezone)->startOfDay();

        $latest = $root->occurrences()->withTrashed()->max('occurrence_date');
        $cursor = CarbonImmutable::parse($latest ?? $root->occurrence_date->toDateString(), $timezone)->startOfDay();
        $rootDate = CarbonImmutable::parse($root->occurrence_date->toDateString(), $timezone)->startOfDay();

        $created = 0;

        while (true) {
            $cursor = $root->recurrence_frequency->next($cursor, $root->recurrence_interval ?? 1);

            if ($cursor->greaterThan($horizon) || ($endsOn !== null && $cursor->greaterThan($endsOn))) {
                break;
            }

            if ($this->createOccurrence($root, $rootDate, $cursor)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Create a single occurrence; returns false when it already exists.
     */
    private function createOccurrence(Task $root, CarbonImmutable $rootDate, CarbonImmutable $date): bool
    {
        try {
            return DB::transaction(function () use ($root, $rootDate, $date): bool {
                $occurrence = $root->replicate([
                    'status', 'completed_at', 'completed_by', 'recurrence_frequency', 'recurrence_interval',
                    'recurrence_ends_on', 'occurrence_date', 'recurrence_parent_id',
                ]);

                $occurrence->recurrence_parent_id = $root->id;
                $occurrence->occurrence_date = $date;
                $occurrence->status = TaskStatus::Pending;
                $occurrence->starts_at = $this->shift($root->starts_at, $rootDate, $date, $root->timezone);
                $occurrence->due_at = $this->shift($root->due_at, $rootDate, $date, $root->timezone);
                $occurrence->save();

                foreach ($root->checklistItems as $item) {
                    $occurrence->checklistItems()->create(['title' => $item->title, 'position' => $item->position]);
                }

                $this->audit->log('task.occurrence_generated', $occurrence, [], [
                    'recurrence_parent_id' => $root->id,
                    'occurrence_date' => $date->toDateString(),
                ]);

                $this->reminders->syncTask($occurrence);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Move a UTC instant by whole local days, keeping its wall-clock time in the time zone.
     */
    private function shift(?CarbonImmutable $instant, CarbonImmutable $from, CarbonImmutable $to, string $timezone): ?CarbonImmutable
    {
        if ($instant === null) {
            return null;
        }

        $local = $instant->setTimezone($timezone);
        $days = (int) $from->diffInDays($to);

        return CarbonImmutable::create(
            $local->year, $local->month, $local->day, $local->hour, $local->minute, 0, $timezone,
        )->addDays($days)->utc();
    }
}
