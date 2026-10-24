<?php

namespace App\Services\Reports;

use App\Enums\EventType;
use App\Enums\Permission;
use App\Enums\PresentationStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\NotificationDelivery;
use App\Models\Presentation;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Builds report tables from live records, applying the same visibility rules as the app.
 *
 * Every report returns the same shape so it can be rendered on screen or exported:
 * a title, ordered columns, string rows, summary figures, and chart points.
 *
 * @phpstan-type ReportBody array{columns: array<string, string>, rows: array<int, array<string, string>>, summary: array<string, int|float|string>, chart: array<int, array{label: string, value: int|float}>}
 */
class ReportBuilder
{
    /**
     * The available reports and the permission each requires.
     *
     * @var array<string, array{title: string, permission: Permission}>
     */
    public const REPORTS = [
        'schedule' => ['title' => 'Schedule summary', 'permission' => Permission::ViewReports],
        'task_completion' => ['title' => 'Task completion', 'permission' => Permission::ViewReports],
        'overdue' => ['title' => 'Overdue tasks', 'permission' => Permission::ViewReports],
        'meeting_frequency' => ['title' => 'Meeting frequency', 'permission' => Permission::ViewReports],
        'presentations' => ['title' => 'Presentation preparation', 'permission' => Permission::ViewReports],
        'deliveries' => ['title' => 'Notification delivery', 'permission' => Permission::ViewReports],
        'audit' => ['title' => 'User activity and audit events', 'permission' => Permission::ViewAuditLogs],
    ];

    /**
     * Get the reports the user may run.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function availableTo(User $user): array
    {
        return collect(self::REPORTS)
            ->filter(fn (array $report) => $user->can($report['permission']->value))
            ->map(fn (array $report, string $key) => ['value' => $key, 'label' => $report['title']])
            ->values()
            ->all();
    }

    /**
     * Build a report for the local date range [from, to] (inclusive) in the user's time zone.
     *
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, string>>, summary: array<string, int|float|string>, chart: array<int, array{label: string, value: int|float}>}
     */
    public function build(string $report, User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $executiveId = null): array
    {
        if (! isset(self::REPORTS[$report]) || ! $user->can(self::REPORTS[$report]['permission']->value)) {
            throw new InvalidArgumentException("Report [{$report}] is not available.");
        }

        $start = $from->setTimezone($user->timezone)->startOfDay()->utc();
        $end = $to->setTimezone($user->timezone)->addDay()->startOfDay()->utc();

        return [
            'title' => self::REPORTS[$report]['title'],
            ...match ($report) {
                'schedule' => $this->schedule($user, $start, $end, $executiveId),
                'task_completion' => $this->taskCompletion($user, $start, $end, $executiveId),
                'overdue' => $this->overdue($user, $executiveId),
                'meeting_frequency' => $this->meetingFrequency($user, $start, $end, $executiveId),
                'presentations' => $this->presentations($user, $start, $end, $executiveId),
                'deliveries' => $this->deliveries($user, $start, $end),
                'audit' => $this->audit($user, $start, $end),
            },
        ];
    }

    /**
     * @return ReportBody
     */
    private function schedule(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $executiveId): array
    {
        $events = $this->events($user, $executiveId)
            ->with('owner:id,name')
            ->overlapping($start, $end)
            ->orderBy('starts_at')
            ->limit(2000)
            ->get();

        $tz = $user->timezone;
        $perDay = $events->groupBy(fn (CalendarEvent $event) => $event->starts_at->setTimezone($tz)->toDateString());

        return [
            'columns' => ['date' => 'Date', 'time' => 'Time', 'title' => 'Title', 'type' => 'Type', 'executive' => 'Executive', 'location' => 'Location', 'status' => 'Status'],
            'rows' => $events->map(fn (CalendarEvent $event) => [
                'date' => $event->starts_at->setTimezone($tz)->format('D, M j, Y'),
                'time' => $event->all_day ? 'All day' : $event->starts_at->setTimezone($tz)->format('g:i A').' – '.$event->ends_at->setTimezone($tz)->format('g:i A'),
                'title' => $event->title,
                'type' => $event->type->label(),
                'executive' => $event->owner->name,
                'location' => (string) $event->location,
                'status' => $event->status->label(),
            ])->all(),
            'summary' => [
                'Appointments' => $events->count(),
                'Cancelled' => $events->filter->isCancelled()->count(),
                'Busiest day' => $perDay->isEmpty() ? '—' : CarbonImmutable::parse($perDay->sortByDesc->count()->keys()->first())->format('D, M j'),
            ],
            'chart' => $perDay->map(fn ($items, string $day) => ['label' => CarbonImmutable::parse($day)->format('M j'), 'value' => $items->count()])->values()->all(),
        ];
    }

    /**
     * @return ReportBody
     */
    private function taskCompletion(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $executiveId): array
    {
        $tasks = $this->tasks($user, $executiveId)
            ->with('assignee:id,name')
            ->where(fn (Builder $query) => $query
                ->whereBetween('due_at', [$start, $end])
                ->orWhereBetween('completed_at', [$start, $end]))
            ->orderBy('due_at')
            ->limit(2000)
            ->get();

        $tz = $user->timezone;
        $completed = $tasks->where('status', TaskStatus::Completed);
        $onTime = $completed->filter(fn (Task $task) => $task->due_at === null || $task->completed_at?->lessThanOrEqualTo($task->due_at));

        return [
            'columns' => ['title' => 'Task', 'assignee' => 'Assignee', 'priority' => 'Priority', 'status' => 'Status', 'due' => 'Due', 'completed' => 'Completed', 'on_time' => 'On time'],
            'rows' => $tasks->map(fn (Task $task) => [
                'title' => $task->title,
                'assignee' => $task->assignee->name ?? 'Unassigned',
                'priority' => $task->priority->label(),
                'status' => $task->status->label(),
                'due' => $task->due_at?->setTimezone($tz)->format('M j, Y g:i A') ?? '—',
                'completed' => $task->completed_at?->setTimezone($tz)->format('M j, Y g:i A') ?? '—',
                'on_time' => $task->status !== TaskStatus::Completed ? '—' : ($onTime->contains($task) ? 'Yes' : 'No'),
            ])->all(),
            'summary' => [
                'Tasks' => $tasks->count(),
                'Completed' => $completed->count(),
                'Completion rate' => $tasks->isEmpty() ? '—' : round($completed->count() / $tasks->count() * 100).'%',
                'Completed on time' => $completed->isEmpty() ? '—' : round($onTime->count() / $completed->count() * 100).'%',
            ],
            'chart' => collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
                'label' => $status->label(),
                'value' => $tasks->where('status', $status)->count(),
            ])->all(),
        ];
    }

    /**
     * @return ReportBody
     */
    private function overdue(User $user, ?int $executiveId): array
    {
        $tasks = $this->tasks($user, $executiveId)->with('assignee:id,name')->overdue()->orderBy('due_at')->limit(2000)->get();
        $tz = $user->timezone;

        return [
            'columns' => ['title' => 'Task', 'assignee' => 'Assignee', 'priority' => 'Priority', 'due' => 'Was due', 'days_overdue' => 'Days overdue'],
            'rows' => $tasks->map(fn (Task $task) => [
                'title' => $task->title,
                'assignee' => $task->assignee->name ?? 'Unassigned',
                'priority' => $task->priority->label(),
                'due' => $task->due_at?->setTimezone($tz)->format('M j, Y g:i A') ?? '—',
                'days_overdue' => (string) (int) $task->due_at?->diffInDays(now()),
            ])->all(),
            'summary' => [
                'Overdue tasks' => $tasks->count(),
                'Urgent or high' => $tasks->filter(fn (Task $task) => in_array($task->priority->value, ['urgent', 'high'], true))->count(),
            ],
            'chart' => $tasks->groupBy(fn (Task $task) => $task->assignee->name ?? 'Unassigned')
                ->map(fn ($items, string $name) => ['label' => $name, 'value' => $items->count()])
                ->values()->all(),
        ];
    }

    /**
     * @return ReportBody
     */
    private function meetingFrequency(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $executiveId): array
    {
        $events = $this->events($user, $executiveId)->active()->overlapping($start, $end)->where('all_day', false)->get(['id', 'type', 'starts_at', 'ends_at']);

        $byType = collect(EventType::cases())->map(fn (EventType $type) => [
            'type' => $type,
            'events' => $events->where('type', $type),
        ])->filter(fn (array $group) => $group['events']->isNotEmpty());

        $hours = fn ($items) => round($items->sum(fn (CalendarEvent $event) => $event->starts_at->diffInMinutes($event->ends_at)) / 60, 1);

        return [
            'columns' => ['type' => 'Type', 'count' => 'Meetings', 'hours' => 'Hours', 'average' => 'Average length (min)'],
            'rows' => $byType->map(fn (array $group) => [
                'type' => $group['type']->label(),
                'count' => (string) $group['events']->count(),
                'hours' => (string) $hours($group['events']),
                'average' => (string) round($group['events']->avg(fn (CalendarEvent $event) => $event->starts_at->diffInMinutes($event->ends_at))),
            ])->values()->all(),
            'summary' => [
                'Meetings' => $events->count(),
                'Hours in meetings' => $hours($events),
            ],
            'chart' => $events->groupBy(fn (CalendarEvent $event) => $event->starts_at->setTimezone($user->timezone)->startOfWeek()->toDateString())
                ->sortKeys()
                ->map(fn ($items, string $week) => ['label' => 'Wk of '.CarbonImmutable::parse($week)->format('M j'), 'value' => $items->count()])
                ->values()->all(),
        ];
    }

    /**
     * @return ReportBody
     */
    private function presentations(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $executiveId): array
    {
        $presentations = Presentation::query()
            ->visibleTo($user)
            ->when($executiveId, fn (Builder $query) => $query->where('owner_id', $executiveId))
            ->with('owner:id,name')
            ->withCount(['attachments', 'checklistItems', 'checklistItems as checklist_done_count' => fn (Builder $query) => $query->where('is_completed', true)])
            ->where(fn (Builder $query) => $query
                ->whereBetween('presentation_at', [$start, $end])
                ->orWhereBetween('preparation_deadline_at', [$start, $end]))
            ->orderBy('presentation_at')
            ->get();

        $tz = $user->timezone;

        return [
            'columns' => ['title' => 'Presentation', 'executive' => 'Executive', 'date' => 'Date', 'prep_deadline' => 'Preparation deadline', 'status' => 'Status', 'files' => 'Files', 'checklist' => 'Checklist'],
            'rows' => $presentations->map(fn (Presentation $presentation) => [
                'title' => $presentation->title,
                'executive' => $presentation->owner->name,
                'date' => $presentation->presentation_at?->setTimezone($tz)->format('M j, Y g:i A') ?? '—',
                'prep_deadline' => $presentation->preparation_deadline_at?->setTimezone($tz)->format('M j, Y g:i A') ?? '—',
                'status' => $presentation->status->label(),
                'files' => (string) $presentation->getAttribute('attachments_count'),
                'checklist' => $presentation->getAttribute('checklist_done_count').'/'.$presentation->getAttribute('checklist_items_count'),
            ])->all(),
            'summary' => [
                'Presentations' => $presentations->count(),
                'Approved or delivered' => $presentations->whereIn('status', [PresentationStatus::Approved, PresentationStatus::Delivered])->count(),
                'Past preparation deadline' => $presentations->filter(fn (Presentation $presentation) => $presentation->isActive() && $presentation->preparation_deadline_at?->isPast())->count(),
            ],
            'chart' => collect(PresentationStatus::cases())->map(fn (PresentationStatus $status) => [
                'label' => $status->label(),
                'value' => $presentations->where('status', $status)->count(),
            ])->all(),
        ];
    }

    /**
     * @return ReportBody
     */
    private function deliveries(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = NotificationDelivery::query()
            ->when(! $user->can(Permission::ViewAllDeliveries->value), fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereBetween('attempted_at', [$start, $end])
            ->selectRaw('notification_type, channel, status, COUNT(*) as aggregate')
            ->groupBy('notification_type', 'channel', 'status')
            ->orderBy('notification_type')
            ->get();

        $total = (int) $rows->sum('aggregate');
        $sent = (int) $rows->filter(fn ($row) => $row->status->value === 'sent')->sum('aggregate');

        return [
            'columns' => ['type' => 'Notification', 'channel' => 'Channel', 'status' => 'Status', 'count' => 'Attempts'],
            'rows' => $rows->map(fn ($row) => [
                'type' => Str::headline($row->notification_type),
                'channel' => $row->channel->label(),
                'status' => $row->status->label(),
                'count' => (string) $row->getAttribute('aggregate'),
            ])->all(),
            'summary' => [
                'Attempts' => $total,
                'Accepted by provider' => $sent,
                'Success rate' => $total === 0 ? '—' : round($sent / $total * 100).'%',
            ],
            'chart' => $rows->groupBy(fn ($row) => $row->status->label())
                ->map(fn ($items, string $status) => ['label' => $status, 'value' => (int) $items->sum('aggregate')])
                ->values()->all(),
        ];
    }

    /**
     * @return ReportBody
     */
    private function audit(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $entries = AuditLog::query()
            ->with('user:id,name')
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->limit(5000)
            ->get();

        $tz = $user->timezone;

        return [
            'columns' => ['when' => 'When', 'user' => 'User', 'action' => 'Action', 'record' => 'Record', 'ip' => 'IP address'],
            'rows' => $entries->map(fn (AuditLog $entry) => [
                'when' => $entry->created_at->setTimezone($tz)->format('M j, Y g:i A'),
                'user' => $entry->user->name ?? 'System',
                'action' => $entry->action,
                'record' => $entry->auditable_type ? Str::headline($entry->auditable_type).' #'.$entry->auditable_id : '—',
                'ip' => (string) $entry->ip_address,
            ])->all(),
            'summary' => [
                'Events' => $entries->count(),
                'Active users' => $entries->pluck('user_id')->filter()->unique()->count(),
                'Sign-ins' => $entries->where('action', 'auth.login')->count(),
            ],
            'chart' => $entries->groupBy(fn (AuditLog $entry) => Str::before($entry->action, '.'))
                ->map(fn ($items, string $area) => ['label' => Str::headline($area), 'value' => $items->count()])
                ->sortByDesc('value')->values()->all(),
        ];
    }

    /**
     * @return Builder<CalendarEvent>
     */
    private function events(User $user, ?int $executiveId): Builder
    {
        return CalendarEvent::query()->visibleTo($user)->when($executiveId, fn (Builder $query) => $query->where('owner_id', $executiveId));
    }

    /**
     * @return Builder<Task>
     */
    private function tasks(User $user, ?int $executiveId): Builder
    {
        return Task::query()->visibleTo($user)->when($executiveId, fn (Builder $query) => $query->where('assigned_to', $executiveId));
    }
}
