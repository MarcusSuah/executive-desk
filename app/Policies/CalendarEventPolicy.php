<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    /**
     * Determine whether the user can view any events. Results are scoped by CalendarEvent::visibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the event.
     */
    public function view(User $user, CalendarEvent $event): bool
    {
        return $user->can(Permission::ManageCalendar->value)
            || $event->owner_id === $user->id
            || $event->attendees()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCalendar->value);
    }

    /**
     * Determine whether the user can edit or reschedule the event.
     */
    public function update(User $user, CalendarEvent $event): bool
    {
        return $user->can(Permission::ManageCalendar->value) && ! $event->isCancelled();
    }

    /**
     * Determine whether the user can cancel the event.
     */
    public function cancel(User $user, CalendarEvent $event): bool
    {
        return $user->can(Permission::ManageCalendar->value) && ! $event->isCancelled();
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, CalendarEvent $event): bool
    {
        return $user->can(Permission::ManageCalendar->value);
    }

    /**
     * Determine whether the user can tick off preparation checklist items.
     */
    public function updateChecklist(User $user, CalendarEvent $event): bool
    {
        return $user->can(Permission::ManageCalendar->value) || $event->owner_id === $user->id;
    }

    /**
     * Determine whether the user can attach files to the event.
     */
    public function attachFiles(User $user, CalendarEvent $event): bool
    {
        return $user->can(Permission::ManageCalendar->value);
    }

    /**
     * Determine whether the user can manage the event's individual reminders.
     */
    public function manageReminders(User $user, CalendarEvent $event): bool
    {
        return $user->can(Permission::ManageCalendar->value) || $event->owner_id === $user->id;
    }
}
