<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the application's built-in roles and permissions.
 *
 * Safe to run repeatedly (seeding, deployments, and tests) and never undoes changes
 * administrators made on the Roles & permissions page: default permissions are only
 * granted when a role or a permission is created for the first time.
 */
class RolesAndPermissions
{
    /**
     * Permissions the Administrator role always keeps, so nobody can be locked out
     * of managing users and roles.
     *
     * @var array<int, Permission>
     */
    public const LOCKED_ADMINISTRATOR_PERMISSIONS = [Permission::ManageUsers, Permission::ManageRoles];

    /**
     * Create missing roles and permissions.
     */
    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newPermissions = [];

        foreach (Permission::cases() as $permission) {
            $model = PermissionModel::findOrCreate($permission->value, 'web');

            if ($model->wasRecentlyCreated) {
                $newPermissions[] = $permission;
            }
        }

        foreach (Role::cases() as $role) {
            $model = RoleModel::findOrCreate($role->value, 'web');
            $defaults = $role->permissions();

            $grant = $model->wasRecentlyCreated
                ? $defaults
                : array_values(array_filter($defaults, fn (Permission $permission) => in_array($permission, $newPermissions, true)));

            if ($role === Role::Administrator) {
                $grant = [...$grant, ...self::LOCKED_ADMINISTRATOR_PERMISSIONS];
            }

            if ($grant !== []) {
                $model->givePermissionTo(array_map(fn (Permission $permission) => $permission->value, $grant));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Determine whether a role name belongs to a built-in role.
     */
    public static function isBuiltIn(string $name): bool
    {
        return Role::tryFrom($name) !== null;
    }

    /**
     * Get a display label for a role name.
     */
    public static function label(string $name): string
    {
        return Role::tryFrom($name)?->label() ?? $name;
    }
}
