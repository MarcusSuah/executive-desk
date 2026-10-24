<?php

namespace App\Services\Briefings;

use App\Enums\BriefingStatus;
use App\Enums\BriefingType;
use App\Enums\EventStatus;
use App\Enums\PresentationStatus;
use App\Enums\Priority;
use App\Enums\ReminderStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\ExecutiveBriefing;
use App\Models\Presentation;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds daily and weekly briefings from live records in the recipient's time zone.
 */
class BriefingGenerator
{
    /**
     * Get the period a briefing covers, as local dates in the user's time zone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodFor(User $user, BriefingType $type, ?CarbonImmutable $localDate = null): array
    {
        $start = ($localDate ?? now($user->timezone))->setTimezone($user->timezone)->startOfDay();

        return $type === BriefingType::Daily ? [$start, $start] : [$start, $start->addDays(6)];
    }

    /**
     * Return the stored briefing for the period, creating it if needed.
     *
     * Safe under concurrency: the (user, type, period_start) unique key means a second
     * generator for the same period gets the first one's row instead of a duplicate.
     */
    public function generate(User $user, BriefingType $type, ?CarbonImmutable $localDate = null, bool $refresh = false): ExecutiveBriefing
    {
        [$start, $end] = $this->periodFor($user, $type, $localDate);

        $existing = $this->find($user, $type, $start);

        if ($existing !== null && ! $refresh) {
            return $existing;
        }

        $content = $type === BriefingType::Daily
            ? $this->dailyContent($user, $start)
            : $this->weeklyContent($user, $start, $end);

        if ($existing !== null) {
            $existing->forceFill(['content' => $content, 'generated_at' => now()])->save();

            return $existing;
        }

        try {
            $briefing = new ExecutiveBriefing;
            $briefing->forceFill([
                'user_id' => $user->id,
                'type' => $type,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'timezone' => $user->timezone,
                'content' => $content,
                'generated_at' => now(),
                'status' => BriefingStatus::Generated,
            ])->save();

            return $briefing;
        } catch (UniqueConstraintViolationException) {
            return $this->find($user, $type, $start) ?? throw new RuntimeException('Briefing could not be stored.');
        }
    }

    /**
     * Build the daily briefing content.
     *
     * @return array<string, mixed>
     */
    public function dailyContent(User $user, CarbonImmutable $day): array
    {
        $from = $day->utc();
        $to = $day->addDay()->utc();

        $appointments = $this->events($user)->overlapping($from, $to)->orderBy('starts_at')->get();
        $dueToday = $this->tasks($user)->open()->whereBetween('due_at', [$from, $to])->orderBy('due_at')->get();
        $highPriority = $this->tasks($user)->open()->whereIn('priority', Priority::elevated())
            ->where(fn (Builder $query) => $query->whereNull('due_at')->orWhere('due_at', '>=', $from))
            ->orderBy('due_at')->limit(10)->get();
        $overdue = $this->tasks($user)->overdue()->orderBy('due_at')->limit(15)->get();
        $presentations = $this->presentations($user, $from, $day->addDays(7)->utc());
        $changes = $this->recentChanges($user, now()->subDay());
        $attention = Reminder::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ReminderStatus::Failed->value, ReminderStatus::Unavailable->value])
            ->where('updated_at', '>=', now()->subDay())
            ->with('remindable')
            ->limit(10)
            ->get();

        return [
            'headline' => $this->headline($appointments->count(), $dueToday->count(), $overdue->count()),
            'summary' => [
                'appointments' => $appointments->count(),
                'tasks_due' => $dueToday->count(),
                'high_priority' => $highPriority->count(),
                'overdue' => $overdue->count(),
            ],
            'sections' => [
                $this->section('appointments', "Today's appointments", 'No appointments today.', $appointments->map(fn (CalendarEvent $event) => $this->eventItem($event, withAgenda: true))),
                $this->section('tasks_due', 'Tasks due today', 'No tasks due today.', $dueToday->map(fn (Task $task) => $this->taskItem($task))),
                $this->section('high_priority', 'High-priority tasks', 'No open high-priority tasks.', $highPriority->map(fn (Task $task) => $this->taskItem($task))),
                $this->section('overdue', 'Overdue tasks', 'Nothing is overdue.', $overdue->map(fn (Task $task) => $this->taskItem($task))),
                $this->section('presentations', 'Presentation deadlines (next 7 days)', 'No presentation deadlines this week.', $presentations->map(fn (Presentation $presentation) => $this->presentationItem($presentation))),
                $this->section('changes', 'Important changes (last 24 hours)', 'No schedule changes.', $changes),
                $this->section('attention', 'Reminders needing attention', 'All reminders were delivered.', $attention->map(fn (Reminder $reminder) => [
                    'id' => $reminder->id,
                    'title' => $reminder->remindable->title ?? 'Removed record',
                    'status' => $reminder->status->value,
                    'detail' => $reminder->channel->label().': '.($reminder->last_error ?? $reminder->status->label()),
                ])),
            ],
        ];
    }

    /**
     * Build the weekly briefing content.
     *
     * @return array<string, mixed>
     */
    public function weeklyContent(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $from = $start->utc();
        $to = $end->addDay()->utc();

        $meetings = $this->events($user)->overlapping($from, $to)->orderBy('starts_at')->get();
        $deadlines = $this->tasks($user)->open()->whereBetween('due_at', [$from, $to])->orderBy('due_at')->get();
        $outstanding = $this->tasks($user)->open()
            ->where(fn (Builder $query) => $query->whereNull('due_at')->orWhere('due_at', '<', $from))
            ->orderBy('due_at')->limit(15)->get();
        $presentations = $this->presentations($user, $from, $to);
        $completed = $this->tasks($user)
            ->where('status', TaskStatus::Completed->value)
            ->whereBetween('completed_at', [$start->subDays(7)->utc(), $from])
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get();
        $important = $deadlines->filter(fn (Task $task) => in_array($task->priority->value, Priority::elevated(), true));

        [$busyDays, $overlaps] = $this->busyPeriods($meetings, $user->timezone);

        return [
            'headline' => sprintf('%d meetings, %d task deadlines and %d presentation deadlines this week.', $meetings->count(), $deadlines->count(), $presentations->count()),
            'summary' => [
                'meetings' => $meetings->count(),
                'task_deadlines' => $deadlines->count(),
                'outstanding' => $outstanding->count(),
                'completed_last_week' => $completed->count(),
            ],
            'sections' => [
                $this->section('meetings', 'Meetings and appointments', 'No meetings this week.', $meetings->map(fn (CalendarEvent $event) => $this->eventItem($event))),
                $this->section('busy', 'Busy periods and overlaps', 'No overloaded days or overlapping commitments.', collect([...$busyDays, ...$overlaps])),
                $this->section('deadlines', 'Task deadlines', 'No task deadlines this week.', $deadlines->map(fn (Task $task) => $this->taskItem($task))),
                $this->section('important', 'Important upcoming deadlines', 'No high-priority deadlines this week.', $important->values()->map(fn (Task $task) => $this->taskItem($task))),
                $this->section('presentations', 'Presentation preparation', 'No presentation deadlines this week.', $presentations->map(fn (Presentation $presentation) => $this->presentationItem($presentation))),
                $this->section('outstanding', 'Outstanding work', 'No outstanding work without a deadline.', $outstanding->map(fn (Task $task) => $this->taskItem($task))),
                $this->section('completed', 'Completed last week', 'No tasks were completed last week.', $completed->map(fn (Task $task) => [
                    ...$this->taskItem($task),
                    'detail' => 'Completed '.$task->completed_at?->toIso8601ZuluString(),
                ])),
            ],
        ];
    }

    /**
     * Find days with more than five hours of meetings and pairs of overlapping meetings.
     *
     * @param  Collection<int, CalendarEvent>  $meetings
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function busyPeriods(Collection $meetings, string $timezone): array
    {
        $timed = $meetings->where('all_day', false)->values();
        $minutesByDay = [];

        foreach ($timed as $event) {
            $day = $event->starts_at->setTimezone($timezone)->toDateString();
            $minutesByDay[$day] = ($minutesByDay[$day] ?? 0) + $event->starts_at->diffInMinutes($event->ends_at);
        }

        $busy = [];
        foreach ($minutesByDay as $day => $minutes) {
            if ($minutes > 300) {
                $busy[] = ['id' => crc32($day), 'title' => 'Busy day: '.CarbonImmutable::parse($day)->format('l, M j'), 'detail' => round($minutes / 60, 1).' hours of meetings'];
            }
        }

        $overlaps = [];
        foreach ($timed as $index => $first) {
            foreach ($timed->slice($index + 1) as $second) {
                if ($second->starts_at->greaterThanOrEqualTo($first->ends_at)) {
                    break;
                }

                if ($first->owner_id === $second->owner_id) {
                    $overlaps[] = [
                        'id' => $first->id * 100000 + $second->id,
                        'title' => "Overlap: {$first->title} / {$second->title}",
                        'starts_at' => $second->starts_at->toIso8601ZuluString(),
                        'url' => route('events.show', $second),
                    ];
                }
            }
        }

        return [$busy, $overlaps];
    }

    /**
     * Collect appointment changes the user should know about.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentChanges(User $user, CarbonImmutable $since): array
    {
        return AuditLog::query()
            ->with(['user:id,name', 'auditable'])
            ->whereIn('action', ['event.rescheduled', 'event.cancelled', 'event.updated'])
            ->where('created_at', '>=', $since)
            ->whereHasMorph('auditable', [CalendarEvent::class], fn (Builder $query) => $query->visibleTo($user)->where(
                fn (Builder $query) => $query->where('owner_id', $user->id)->orWhereHas('attendees', fn (Builder $query) => $query->where('user_id', $user->id))
            ))
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'title' => Str::headline(str_replace('event.', '', $log->action)).': '.($log->auditable?->getAttribute('title') ?? 'Appointment'),
                'detail' => 'By '.($log->user->name ?? 'system'),
                'starts_at' => $log->auditable?->getAttribute('starts_at')?->toIso8601ZuluString(),
                'url' => $log->auditable ? route('events.show', $log->auditable_id) : null,
            ])
            ->all();
    }

    /**
     * Get the events relevant to the user: those on their own calendar or that they attend,
     * plus everything they manage.
     *
     * @return Builder<CalendarEvent>
     */
    private function events(User $user): Builder
    {
        return CalendarEvent::query()->visibleTo($user)->where('status', '!=', EventStatus::Cancelled->value);
    }

    /**
     * Get the tasks relevant to the user.
     *
     * @return Builder<Task>
     */
    private function tasks(User $user): Builder
    {
        return Task::query()->visibleTo($user);
    }

    /**
     * Get active presentations with a deadline or presentation date in the range.
     *
     * @return Collection<int, Presentation>
     */
    private function presentations(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Presentation::query()
            ->visibleTo($user)
            ->whereIn('status', PresentationStatus::active())
            ->where(fn (Builder $query) => $query
                ->whereBetween('preparation_deadline_at', [$from, $to])
                ->orWhereBetween('review_deadline_at', [$from, $to])
                ->orWhereBetween('presentation_at', [$from, $to]))
            ->orderByRaw('COALESCE(preparation_deadline_at, review_deadline_at, presentation_at)')
            ->get();
    }

    /**
     * Build a section.
     *
     * @param  iterable<int, array<string, mixed>>  $items
     * @return array{key: string, title: string, empty: string, items: array<int, array<string, mixed>>}
     */
    private function section(string $key, string $title, string $empty, iterable $items): array
    {
        return ['key' => $key, 'title' => $title, 'empty' => $empty, 'items' => array_values(collect($items)->all())];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventItem(CalendarEvent $event, bool $withAgenda = false): array
    {
        return array_filter([
            'id' => $event->id,
            'title' => $event->title,
            'starts_at' => $event->starts_at->toIso8601ZuluString(),
            'ends_at' => $event->ends_at->toIso8601ZuluString(),
            'location' => $event->location,
            'meeting_url' => $event->meeting_url,
            'agenda' => $withAgenda ? Str::limit((string) $event->agenda, 500) : null,
            'priority' => $event->priority->value,
            'url' => route('events.show', $event),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function taskItem(Task $task): array
    {
        return array_filter([
            'id' => $task->id,
            'title' => $task->title,
            'due_at' => $task->due_at?->toIso8601ZuluString(),
            'priority' => $task->priority->value,
            'status' => $task->status->value,
            'url' => route('tasks.show', $task),
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentationItem(Presentation $presentation): array
    {
        return array_filter([
            'id' => $presentation->id,
            'title' => $presentation->title,
            'deadline' => ($presentation->preparation_deadline_at ?? $presentation->review_deadline_at ?? $presentation->presentation_at)?->toIso8601ZuluString(),
            'starts_at' => $presentation->presentation_at?->toIso8601ZuluString(),
            'status' => $presentation->status->value,
            'url' => route('presentations.show', $presentation),
        ], fn ($value) => $value !== null);
    }

    /**
     * Summarize the day in one sentence.
     */
    private function headline(int $appointments, int $tasksDue, int $overdue): string
    {
        $parts = [
            trans_choice('{0} No appointments|{1} 1 appointment|[2,*] :count appointments', $appointments),
            trans_choice('{0} no tasks due|{1} 1 task due|[2,*] :count tasks due', $tasksDue),
        ];

        if ($overdue > 0) {
            $parts[] = trans_choice('{1} 1 overdue task|[2,*] :count overdue tasks', $overdue);
        }

        return implode(', ', $parts).' today.';
    }

    /**
     * Find a stored briefing for a period.
     */
    private function find(User $user, BriefingType $type, CarbonImmutable $start): ?ExecutiveBriefing
    {
        return ExecutiveBriefing::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->whereDate('period_start', $start->toDateString())
            ->first();
    }
}
