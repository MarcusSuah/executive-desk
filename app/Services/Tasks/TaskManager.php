<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Support\Facades\DB;

class TaskManager
{
    public function __construct(
        private ReminderPlanner $reminders,
        private AuditLogger $audit,
    ) {}

    /**
     * Create a task (and, for a recurring task, its first occurrence date).
     *
     * @param  array<string, mixed>  $attributes  Validated attributes with UTC due_at/starts_at.
     * @param  array<int, string>  $checklist
     */
    public function create(array $attributes, array $checklist, User $actor): Task
    {
        return DB::transaction(function () use ($attributes, $checklist, $actor): Task {
            $task = new Task($attributes);
            $task->created_by = $actor->id;

            if ($task->recurrence_frequency !== null) {
                $anchor = $task->due_at ?? $task->starts_at ?? now();
                $task->occurrence_date = $anchor->setTimezone($task->timezone)->startOfDay();
            }

            $task->save();

            foreach (array_values($checklist) as $position => $title) {
                $task->checklistItems()->create(['title' => $title, 'position' => $position]);
            }

            $this->audit->log('task.created', $task, [], $task->only([
                'title', 'assigned_to', 'priority', 'due_at', 'recurrence_frequency',
            ]), $actor);

            if ($task->assigned_to !== null && $task->assigned_to !== $actor->id) {
                $this->audit->log('task.assigned', $task, [], ['assigned_to' => $task->assigned_to], $actor);
            }

            $this->reminders->syncTask($task);

            return $task;
        });
    }

    /**
     * Update task details, including reassignment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Task $task, array $attributes, User $actor): Task
    {
        return DB::transaction(function () use ($task, $attributes, $actor): Task {
            $task->fill($attributes)->save();

            $this->audit->logChanges(
                $task->wasChanged('assigned_to') ? 'task.reassigned' : 'task.updated',
                $task,
                $actor,
            );

            $this->reminders->syncTask($task);

            return $task;
        });
    }

    /**
     * Change a task's progress status, tracking completion.
     */
    public function changeStatus(Task $task, TaskStatus $status, User $actor): Task
    {
        return DB::transaction(function () use ($task, $status, $actor): Task {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($task->status === $status) {
                return $task;
            }

            $previous = $task->status;

            $task->status = $status;
            $task->completed_at = $status === TaskStatus::Completed ? now() : null;
            $task->completed_by = $status === TaskStatus::Completed ? $actor->id : null;
            $task->save();

            $action = match ($status) {
                TaskStatus::Completed => 'task.completed',
                TaskStatus::Cancelled => 'task.cancelled',
                default => $previous === TaskStatus::Completed ? 'task.reopened' : 'task.status_changed',
            };

            $this->audit->log($action, $task, ['status' => $previous->value], ['status' => $status->value], $actor);

            $this->reminders->syncTask($task);

            return $task;
        });
    }

    /**
     * Soft delete a task.
     */
    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor): void {
            $this->reminders->cancelAll($task, 'Task deleted');
            $task->delete();

            $this->audit->log('task.deleted', $task, $task->only(['title', 'assigned_to', 'due_at']), [], $actor);
        });
    }
}
