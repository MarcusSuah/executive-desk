<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Priority;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskStatus;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\TaskResource;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Services\Tasks\TaskManager;
use App\Support\FormOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * The task list tabs.
     *
     * @var array<int, string>
     */
    private const TABS = ['all', 'pending', 'in_progress', 'completed', 'overdue', 'cancelled'];

    public function __construct(private TaskManager $tasks) {}

    /**
     * Display the task list or board.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'q' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'assignee' => ['nullable', 'integer'],
            'layout' => ['nullable', Rule::in(['list', 'board'])],
            'sort' => ['nullable', Rule::in(['due_at', 'priority', 'created_at'])],
        ]);

        $user = $request->user();
        $tab = $filters['tab'] ?? 'all';
        $layout = $filters['layout'] ?? 'list';

        $base = Task::query()
            ->visibleTo($user)
            ->when($filters['q'] ?? null, fn (Builder $query, $search) => $query->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('project', 'like', "%{$search}%")))
            ->when($filters['priority'] ?? null, fn (Builder $query, $priority) => $query->where('priority', $priority))
            ->when($filters['assignee'] ?? null, fn (Builder $query, $assignee) => $query->where('assigned_to', $assignee));

        $counts = [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', TaskStatus::Pending->value)->count(),
            'in_progress' => (clone $base)->where('status', TaskStatus::InProgress->value)->count(),
            'completed' => (clone $base)->where('status', TaskStatus::Completed->value)->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'cancelled' => (clone $base)->where('status', TaskStatus::Cancelled->value)->count(),
        ];

        $query = (clone $base)
            ->with(['assignee:id,name,email,timezone'])
            ->withCount(['checklistItems', 'completedChecklistItems'])
            ->when($tab === 'overdue', fn (Builder $query) => $query->overdue())
            ->when(! in_array($tab, ['all', 'overdue'], true), fn (Builder $query) => $query->where('status', $tab));

        match ($filters['sort'] ?? 'due_at') {
            'priority' => $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->orderBy('due_at'),
            'created_at' => $query->latest(),
            default => $query->orderByRaw('due_at IS NULL')->orderBy('due_at'),
        };

        $tasks = $layout === 'board'
            ? TaskResource::collection($query->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value, TaskStatus::Completed->value])->limit(300)->get())
            : TaskResource::collection($query->paginate(20)->withQueryString());

        return Inertia::render('tasks/index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'filters' => [
                'tab' => $tab,
                'q' => $filters['q'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'assignee' => isset($filters['assignee']) ? (string) $filters['assignee'] : '',
                'layout' => $layout,
                'sort' => $filters['sort'] ?? 'due_at',
            ],
            'options' => [
                'priorities' => Priority::options(),
                'statuses' => TaskStatus::options(),
                'users' => $user->can(Permission::ManageTasks->value) ? FormOptions::users() : [],
            ],
            'canCreate' => $user->can('create', Task::class),
        ]);
    }

    /**
     * Show the form for creating a task.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Task::class);

        return Inertia::render('tasks/form', [
            'task' => null,
            'prefill' => $request->validate(['event' => ['nullable', 'integer'], 'assignee' => ['nullable', 'integer']]),
            'options' => $this->formOptions($request),
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = $this->tasks->create($request->taskAttributes(), $request->checklist(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return to_route('tasks.show', $task);
    }

    /**
     * Display a task.
     */
    public function show(Request $request, Task $task): Response
    {
        Gate::authorize('view', $task);

        $user = $request->user();

        $task->load(['assignee', 'creator', 'completer', 'event', 'checklistItems', 'attachments.uploader']);

        return Inertia::render('tasks/show', [
            'task' => new TaskResource($task),
            'history' => AuditLogResource::collection($task->auditLogs()->with('user:id,name')->limit(50)->get()),
            'statuses' => TaskStatus::options(),
            'can' => [
                'update' => $user->can('update', $task),
                'updateProgress' => $user->can('updateProgress', $task),
                'delete' => $user->can('delete', $task),
            ],
        ]);
    }

    /**
     * Show the form for editing a task.
     */
    public function edit(Request $request, Task $task): Response
    {
        Gate::authorize('update', $task);

        return Inertia::render('tasks/form', [
            'task' => new TaskResource($task),
            'prefill' => [],
            'options' => $this->formOptions($request),
        ]);
    }

    /**
     * Update a task.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->update($task, $request->taskAttributes(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task updated.')]);

        return to_route('tasks.show', $task);
    }

    /**
     * Change a task's status.
     */
    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('updateProgress', $task);

        $validated = $request->validate(['status' => ['required', Rule::enum(TaskStatus::class)]]);

        $this->tasks->changeStatus($task, TaskStatus::from($validated['status']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task status updated.')]);

        return back();
    }

    /**
     * Delete a task.
     */
    public function destroy(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $this->tasks->delete($task, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task deleted.')]);

        return to_route('tasks.index');
    }

    /**
     * Get the option lists for the task form.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'users' => FormOptions::users(),
            'priorities' => Priority::options(),
            'frequencies' => RecurrenceFrequency::options(),
            'timezones' => FormOptions::timezones(),
            'events' => CalendarEvent::query()
                ->visibleTo($request->user())
                ->active()
                ->where('starts_at', '>=', now()->subDays(7))
                ->orderBy('starts_at')
                ->limit(100)
                ->get(['id', 'title', 'starts_at'])
                ->map(fn (CalendarEvent $event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'starts_at' => $event->starts_at->toIso8601ZuluString(),
                ]),
        ];
    }
}
