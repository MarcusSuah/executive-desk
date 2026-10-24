<?php

namespace App\Services\Dashboard;

use App\Enums\BriefingType;
use App\Enums\Permission;
use App\Enums\PresentationStatus;
use App\Enums\Priority;
use App\Enums\ReminderStatus;
use App\Enums\TaskStatus;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\CalendarEventResource;
use App\Http\Resources\ExecutiveBriefingResource;
use App\Http\Resources\PresentationResource;
use App\Http\Resources\TaskResource;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\ExecutiveBriefing;
use App\Models\NotificationDelivery;
use App\Models\Presentation;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the dashboard from live records, scoped to what the viewer may see and,
 * for assistants, optionally narrowed to one executive.
 */
class DashboardSummary
{
    /**
     * Build the dashboard data.
     *
     * @return array<string, mixed>
     */
    public function for(User $user, ?int $executiveId = null): array
    {
        $timezone = $user->timezone;
        $now = now();
        $todayStart = $now->setTimezone($timezone)->startOfDay();
        $todayEnd = $todayStart->addDay();
        $weekEnd = $todayStart->addDays(7);

        $events = fn (): Builder => CalendarEvent::query()
            ->visibleTo($user)
            ->active()
            ->when($executiveId, fn (Builder $query) => $query->where('owner_id', $executiveId));

        $tasks = fn (): Builder => Task::query()
            ->visibleTo($user)
            ->when($executiveId, fn (Builder $query) => $query->where('assigned_to', $executiveId));

        $todaysEvents = $events()
            ->with('owner:id,name,email,timezone')
            ->overlapping($todayStart->utc(), $todayEnd->utc())
            ->orderBy('starts_at')
            ->limit(25)
            ->get();

        $nextEvent = $events()
            ->with(['owner:id,name,email,timezone', 'attendees'])
            ->where('starts_at', '>', $now)
            ->orderBy('starts_at')
            ->first();

        $upcoming = $events()
            ->with('owner:id,name,email,timezone')
            ->where('starts_at', '>=', $todayEnd->utc())
            ->where('starts_at', '<', $weekEnd->utc())
            ->orderBy('starts_at')
            ->limit(15)
            ->get();

        $taskCounts = [
            'pending' => $tasks()->where('status', TaskStatus::Pending->value)->count(),
            'in_progress' => $tasks()->where('status', TaskStatus::InProgress->value)->count(),
            'overdue' => $tasks()->overdue()->count(),
            'high_priority' => $tasks()->open()->whereIn('priority', Priority::elevated())->count(),
            'completed_this_week' => $tasks()
                ->where('status', TaskStatus::Completed->value)
                ->where('completed_at', '>=', $todayStart->startOfWeek(CarbonImmutable::MONDAY)->utc())
                ->count(),
        ];

        $dueToday = $tasks()
            ->where('status', '!=', TaskStatus::Cancelled->value)
            ->whereBetween('due_at', [$todayStart->utc(), $todayEnd->utc()]);

        $dailyCompletion = [
            'due_today' => (clone $dueToday)->count(),
            'completed_today' => (clone $dueToday)->where('status', TaskStatus::Completed->value)->count(),
            'completed_any_today' => $tasks()
                ->where('status', TaskStatus::Completed->value)
                ->whereBetween('completed_at', [$todayStart->utc(), $todayEnd->utc()])
                ->count(),
        ];

        $taskList = fn (Builder $query) => TaskResource::collection(
            $query->with('assignee:id,name,email,timezone')->orderBy('due_at')->limit(6)->get()
        );

        return [
            'now' => $now->toIso8601ZuluString(),
            'today' => $todayStart->toDateString(),
            'todaysEvents' => CalendarEventResource::collection($todaysEvents),
            'nextEvent' => $nextEvent ? new CalendarEventResource($nextEvent) : null,
            'upcomingEvents' => CalendarEventResource::collection($upcoming),
            'meetingLoad' => $this->meetingLoad($events(), $todayStart),
            'taskCounts' => $taskCounts,
            'dailyCompletion' => $dailyCompletion,
            'overdueTasks' => $taskList($tasks()->overdue()),
            'priorityTasks' => $taskList($tasks()->open()->whereIn('priority', Priority::elevated())),
            'inProgressTasks' => $taskList($tasks()->where('status', TaskStatus::InProgress->value)),
            'presentationDeadlines' => PresentationResource::collection(
                Presentation::query()
                    ->visibleTo($user)
                    ->when($executiveId, fn (Builder $query) => $query->where('owner_id', $executiveId))
                    ->whereIn('status', PresentationStatus::active())
                    ->where(fn (Builder $query) => $query
                        ->where('preparation_deadline_at', '>=', $now->subDay())
                        ->orWhere('presentation_at', '>=', $now))
                    ->orderByRaw('COALESCE(preparation_deadline_at, presentation_at)')
                    ->limit(5)
                    ->get()
            ),
            'recentChanges' => AuditLogResource::collection(
                AuditLog::query()
                    ->with(['user:id,name', 'auditable'])
                    ->whereIn('action', ['event.created', 'event.rescheduled', 'event.updated', 'event.cancelled'])
                    ->where('created_at', '>=', $now->subDays(7))
                    ->whereHasMorph('auditable', [CalendarEvent::class], fn (Builder $query) => $query
                        ->visibleTo($user)
                        ->when($executiveId, fn (Builder $query) => $query->where('owner_id', $executiveId)))
                    ->latest('id')
                    ->limit(8)
                    ->get()
            ),
            'upcomingReminders' => $this->upcomingReminders($user),
            'deliveryStatus' => $this->deliveryStatus($user),
            'briefings' => [
                'daily' => $this->latestBriefing($user, $executiveId, BriefingType::Daily),
                'weekly' => $this->latestBriefing($user, $executiveId, BriefingType::Weekly),
            ],
        ];
    }

    /**
     * Count meetings and booked hours for each of the next seven days.
     *
     * @param  Builder<CalendarEvent>  $events
     * @return array<int, array{date: string, meetings: int, hours: float}>
     */
    private function meetingLoad(Builder $events, CarbonImmutable $todayStart): array
    {
        $rows = (clone $events)
            ->where('all_day', false)
            ->overlapping($todayStart->utc(), $todayStart->addDays(7)->utc())
            ->get(['id', 'starts_at', 'ends_at']);

        $load = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $dayStart = $todayStart->addDays($offset);
            $dayEnd = $dayStart->addDay();
            $minutes = 0;
            $meetings = 0;

            foreach ($rows as $event) {
                $start = $event->starts_at->max($dayStart);
                $end = $event->ends_at->min($dayEnd);

                if ($end->greaterThan($start)) {
                    $meetings++;
                    $minutes += $start->diffInMinutes($end);
                }
            }

            $load[] = ['date' => $dayStart->toDateString(), 'meetings' => $meetings, 'hours' => round($minutes / 60, 1)];
        }

        return $load;
    }

    /**
     * Get the viewer's own reminders due in the next 24 hours.
     *
     * @return array<int, array<string, mixed>>
     */
    private function upcomingReminders(User $user): array
    {
        return Reminder::query()
            ->where('user_id', $user->id)
            ->where('status', ReminderStatus::Pending->value)
            ->whereBetween('scheduled_for', [now(), now()->addDay()])
            ->with('remindable')
            ->orderBy('scheduled_for')
            ->limit(8)
            ->get()
            ->map(fn (Reminder $reminder) => [
                'id' => $reminder->id,
                'title' => $reminder->remindable->title ?? 'Removed record',
                'kind' => $reminder->kind->value,
                'channel' => $reminder->channel->label(),
                'minutes_before' => DisplayTime::minutes($reminder->minutes_before),
                'scheduled_for' => $reminder->scheduled_for->toIso8601ZuluString(),
            ])
            ->all();
    }

    /**
     * Summarize delivery outcomes over the last seven days.
     *
     * @return array{scope: string, counts: array<string, int>, pending: int}
     */
    private function deliveryStatus(User $user): array
    {
        $all = $user->can(Permission::ViewAllDeliveries->value);

        $counts = NotificationDelivery::query()
            ->when(! $all, fn (Builder $query) => $query->where('user_id', $user->id))
            ->where('attempted_at', '>=', now()->subDays(7))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $pending = Reminder::query()
            ->when(! $all, fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereIn('status', ReminderStatus::active())
            ->where('scheduled_for', '<=', now()->addDay())
            ->count();

        return ['scope' => $all ? 'all' : 'mine', 'counts' => $counts, 'pending' => $pending];
    }

    /**
     * Get the most recent briefing of a type for the viewer or the selected executive.
     */
    private function latestBriefing(User $user, ?int $executiveId, BriefingType $type): ?ExecutiveBriefingResource
    {
        $recipientId = $executiveId !== null && $user->can(Permission::ManageBriefings->value) ? $executiveId : $user->id;

        $briefing = ExecutiveBriefing::query()
            ->where('user_id', $recipientId)
            ->where('type', $type->value)
            ->latest('period_start')
            ->first();

        return $briefing ? new ExecutiveBriefingResource($briefing) : null;
    }
}
