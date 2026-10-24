<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Models\User;

class PresentationPolicy
{
    /**
     * Determine whether the user can view any presentations. Results are scoped by Presentation::visibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the presentation.
     */
    public function view(User $user, Presentation $presentation): bool
    {
        return $user->can(Permission::ManagePresentations->value)
            || $presentation->owner_id === $user->id
            || $presentation->presenter_id === $user->id;
    }

    /**
     * Determine whether the user can create presentations.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ManagePresentations->value);
    }

    /**
     * Determine whether the user can edit the presentation.
     */
    public function update(User $user, Presentation $presentation): bool
    {
        return $user->can(Permission::ManagePresentations->value);
    }

    /**
     * Determine whether the user can delete the presentation.
     */
    public function delete(User $user, Presentation $presentation): bool
    {
        return $user->can(Permission::ManagePresentations->value);
    }

    /**
     * Determine whether the user can approve the presentation.
     */
    public function approve(User $user, Presentation $presentation): bool
    {
        if ($presentation->status === PresentationStatus::Approved || ! $presentation->isActive()) {
            return false;
        }

        return $user->can(Permission::ManagePresentations->value) || $presentation->owner_id === $user->id;
    }

    /**
     * Determine whether the user can upload files and update the checklist.
     */
    public function contribute(User $user, Presentation $presentation): bool
    {
        return $user->can(Permission::ManagePresentations->value)
            || $presentation->owner_id === $user->id
            || $presentation->presenter_id === $user->id;
    }
}
