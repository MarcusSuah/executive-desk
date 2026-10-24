<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\Permission;
use App\Enums\Priority;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Support\FormOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * The supported calendar views.
     *
     * @var array<int, string>
     */
    private const VIEWS = ['day', 'week', 'month', 'agenda'];

    /**
     * Show the calendar for a day, week, month, or agenda range in the viewer's time zone.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'view' => ['nullable', Rule::in(self::VIEWS)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(EventType::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'status' => ['nullable', Rule::enum(EventStatus::class)],
            'executive' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $timezone = $user->timezone;
        $view = $filters['view'] ?? 'week';
        $date = isset($filters['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['date'], $timezone)->startOfDay()
            : now($timezone)->startOfDay();

        [$rangeStart, $rangeEnd, $previous, $next] = $this->range($view, $date);

        $events = CalendarEvent::query()
            ->visibleTo($user)
            ->with('owner:id,name,email,timezone')
            ->overlapping($rangeStart->utc(), $rangeEnd->utc())
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
                fn ($query) => $query->active(),
            )
            ->when($filters['executive'] ?? null, fn ($query, $ownerId) => $query->where('owner_id', $ownerId))
            ->orderBy('starts_at')
            ->limit(1000)
            ->get();

        $days = [];
        for ($day = $rangeStart; $day->lessThan($rangeEnd); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return Inertia::render('calendar/index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'today' => now($timezone)->toDateString(),
            'days' => $days,
            'previousDate' => $previous->toDateString(),
            'nextDate' => $next->toDateString(),
            'events' => CalendarEventResource::collection($events),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'type' => $filters['type'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'status' => $filters['status'] ?? '',
                'executive' => isset($filters['executive']) ? (string) $filters['executive'] : '',
            ],
            'options' => [
                'types' => EventType::options(),
                'priorities' => Priority::options(),
                'statuses' => EventStatus::options(),
                'executives' => $user->can(Permission::ManageCalendar->value) ? FormOptions::executives() : [],
            ],
            'canCreate' => $user->can('create', CalendarEvent::class),
        ]);
    }

    /**
     * Get the local range [start, end) for the view and the dates of the previous and next periods.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    private function range(string $view, CarbonImmutable $date): array
    {
        return match ($view) {
            'day' => [$date, $date->addDay(), $date->subDay(), $date->addDay()],
            'month' => [
                $date->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY),
                $date->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->addDay()->startOfDay(),
                $date->startOfMonth()->subMonth(),
                $date->startOfMonth()->addMonth(),
            ],
            'agenda' => [$date, $date->addDays(30), $date->subDays(30), $date->addDays(30)],
            default => [
                $date->startOfWeek(CarbonImmutable::MONDAY),
                $date->startOfWeek(CarbonImmutable::MONDAY)->addWeek(),
                $date->subWeek(),
                $date->addWeek(),
            ],
        };
    }
}
