<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    /**
     * Determine whether the user can switch an individual upcoming reminder off or on.
     */
    public function toggle(User $user, Reminder $reminder): bool
    {
        if (! in_array($reminder->status, [ReminderStatus::Pending, ReminderStatus::Disabled], true)) {
            return false;
        }

        return $reminder->user_id === $user->id
            || ($reminder->remindable !== null && $user->can('manageReminders', $reminder->remindable));
    }

    /**
     * Determine whether the user can retry a failed reminder.
     */
    public function retry(User $user, Reminder $reminder): bool
    {
        return $reminder->status === ReminderStatus::Failed
            && $user->can(Permission::RetryDeliveries->value);
    }
}
