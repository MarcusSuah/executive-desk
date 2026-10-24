<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks. Results are scoped by Task::visibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->can(Permission::ManageTasks->value)
            || $task->assigned_to === $user->id
            || $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can create and assign tasks.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ManageTasks->value);
    }

    /**
     * Determine whether the user can edit all task details, including reassignment.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->can(Permission::ManageTasks->value);
    }

    /**
     * Determine whether the user can change progress: status, checklist, and attachments.
     */
    public function updateProgress(User $user, Task $task): bool
    {
        return $user->can(Permission::ManageTasks->value) || $task->assigned_to === $user->id;
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->can(Permission::ManageTasks->value);
    }
}
