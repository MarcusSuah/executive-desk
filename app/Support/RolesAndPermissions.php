<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the application's roles and permissions and keeps their mapping in sync.
 * Safe to run repeatedly (seeding, deployments, and tests).
 */
class RolesAndPermissions
{
    /**
     * Create missing roles and permissions and sync each role's permissions.
     */
    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')->syncPermissions(
                array_map(fn (Permission $permission) => $permission->value, $role->permissions()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
