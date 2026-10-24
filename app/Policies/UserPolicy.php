<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can list user accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    /**
     * Determine whether the user can create accounts.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    /**
     * Determine whether the user can edit the account and its roles.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    /**
     * Determine whether the user can deactivate or reactivate the account.
     */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->can(Permission::ManageUsers->value) && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can permanently remove the account.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::ManageUsers->value) && $user->id !== $model->id;
    }
}
