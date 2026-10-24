<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role as BuiltInRole;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\RolesAndPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Roles & permissions: which permissions each role grants.
 *
 * Built-in roles (Administrator, Executive Assistant, Executive) can have their
 * permissions changed but cannot be renamed or deleted, because the application
 * relies on them (for example, appointments belong to executives). Administrators
 * can add any number of custom roles.
 */
class RoleController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Show every role with its permissions.
     */
    public function index(): Response
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderByRaw('CASE name WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', [
                BuiltInRole::Administrator->value, BuiltInRole::Assistant->value, BuiltInRole::Executive->value,
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => RolesAndPermissions::label($role->name),
                'description' => BuiltInRole::tryFrom($role->name)?->description() ?? $role->getAttribute('description'),
                'built_in' => RolesAndPermissions::isBuiltIn($role->name),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'users_count' => (int) $role->getAttribute('users_count'),
            ]);

        return Inertia::render('admin/roles', [
            'roles' => $roles,
            'permissions' => self::permissionCatalogue(),
            'locked' => [
                BuiltInRole::Administrator->value => array_map(
                    fn (Permission $permission) => $permission->value,
                    RolesAndPermissions::LOCKED_ADMINISTRATOR_PERMISSIONS,
                ),
            ],
        ]);
    }

    /**
     * Create a custom role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-_&]*$/u', Rule::unique('roles', 'name'), Rule::notIn(array_map(fn (BuiltInRole $role) => $role->label(), BuiltInRole::cases()))],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['distinct', Rule::enum(Permission::class)],
        ], [
            'name.regex' => 'Use letters, numbers, spaces, hyphens, underscores, or ampersands.',
            'name.not_in' => 'That name belongs to a built-in role.',
        ]);

        $role = DB::transaction(function () use ($validated, $request): Role {
            $role = new Role;
            $role->forceFill(['name' => trim($validated['name']), 'guard_name' => 'web', 'description' => $validated['description'] ?? null])->save();
            $role->syncPermissions($validated['permissions'] ?? []);

            $this->audit->log('role.created', $role, [], [
                'name' => $role->name,
                'permissions' => $validated['permissions'] ?? [],
            ], $request->user());

            return $role;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role “:name” created.', ['name' => $role->name])]);

        return back();
    }

    /**
     * Update a role's permissions (and, for custom roles, its name and description).
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $builtIn = RolesAndPermissions::isBuiltIn($role->name);

        $validated = $request->validate([
            'name' => [$builtIn ? 'prohibited' : 'required', 'string', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-_&]*$/u', Rule::unique('roles', 'name')->ignore($role->id), Rule::notIn(array_map(fn (BuiltInRole $role) => $role->label(), BuiltInRole::cases()))],
            'description' => [$builtIn ? 'prohibited' : 'nullable', 'string', 'max:500'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['distinct', Rule::enum(Permission::class)],
        ], [
            'name.regex' => 'Use letters, numbers, spaces, hyphens, underscores, or ampersands.',
            'name.not_in' => 'That name belongs to a built-in role.',
            'name.prohibited' => 'Built-in roles cannot be renamed.',
        ]);

        // Already validated above: every entry is a known, distinct permission name.
        $permissions = $request->collect('permissions')->sort()->values()->all();

        if ($role->name === BuiltInRole::Administrator->value) {
            $missing = array_filter(
                RolesAndPermissions::LOCKED_ADMINISTRATOR_PERMISSIONS,
                fn (Permission $permission) => ! in_array($permission->value, $permissions, true),
            );

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'permissions' => 'The Administrator role must keep “Manage users” and “Manage roles & permissions” so nobody is locked out.',
                ]);
            }
        }

        DB::transaction(function () use ($role, $validated, $permissions, $builtIn, $request): void {
            $before = ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->sort()->values()->all()];

            if (! $builtIn) {
                $role->forceFill(['name' => trim($validated['name']), 'description' => $validated['description'] ?? null])->save();
            }

            $role->syncPermissions($permissions);

            $after = ['name' => $role->name, 'permissions' => $permissions];

            if ($before !== $after) {
                $this->audit->log('role.updated', $role, $before, $after, $request->user());
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role “:name” saved.', ['name' => RolesAndPermissions::label($role->name)])]);

        return back();
    }

    /**
     * Delete a custom role. People who had it keep their other roles.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if (RolesAndPermissions::isBuiltIn($role->name)) {
            throw ValidationException::withMessages(['role' => 'Built-in roles cannot be deleted.']);
        }

        DB::transaction(function () use ($role, $request): void {
            $this->audit->log('role.deleted', $role, [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->all(),
                'users' => $role->users()->count(),
            ], [], $request->user());

            $role->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role “:name” deleted.', ['name' => $role->name])]);

        return back();
    }

    /**
     * Describe every permission for the screens that assign them.
     *
     * @return array<int, array{value: string, label: string, description: string, group: string}>
     */
    public static function permissionCatalogue(): array
    {
        return array_map(fn (Permission $permission) => [
            'value' => $permission->value,
            'label' => $permission->label(),
            'description' => $permission->description(),
            'group' => $permission->group(),
        ], Permission::cases());
    }
}
