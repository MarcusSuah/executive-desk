<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Reminders\ReminderPlanner;
use App\Support\AppSettings;
use App\Support\FormOptions;
use App\Support\RolesAndPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as SpatieRole;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * List user accounts.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->with(['roles:id,name', 'permissions:id,name'])
            ->when($filters['q'] ?? null, fn (Builder $query, $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['role'] ?? null, fn (Builder $query, $role) => $query->role($role))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'roles' => $this->roleOptions(),
        ]);
    }

    /**
     * Show the form for creating an account.
     */
    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/form', [
            'user' => null,
            'roles' => $this->roleOptions(),
            'permissions' => RoleController::permissionCatalogue(),
            'canManagePermissions' => request()->user()->can(Permission::ManageRoles->value),
            'timezones' => FormOptions::timezones(),
            'defaultTimezone' => AppSettings::defaultTimezone(),
        ]);
    }

    /**
     * Create an account and email the user a link to set their password.
     */
    public function store(UserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                ...$request->safe()->only(['name', 'email', 'timezone', 'phone']),
                'password' => Str::password(40),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles($request->validated('roles'));

            if ($request->has('permissions')) {
                $user->syncPermissions($request->validated('permissions', []));
            }

            $this->audit->log('user.created', $user, [], [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $request->validated('roles'),
                'permissions' => $request->validated('permissions', []),
            ], $request->user());

            return $user;
        });

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', [
            'type' => $status === Password::RESET_LINK_SENT ? 'success' : 'error',
            'message' => $status === Password::RESET_LINK_SENT
                ? __('Account created. A link to set the password was emailed to :email.', ['email' => $user->email])
                : __('Account created, but the password link could not be sent: :status', ['status' => __($status)]),
        ]);

        return to_route('admin.users.index');
    }

    /**
     * Show the form for editing an account.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/form', [
            'user' => $this->present($user->load(['roles:id,name', 'permissions:id,name'])),
            'roles' => $this->roleOptions(),
            'permissions' => RoleController::permissionCatalogue(),
            'canManagePermissions' => request()->user()->can(Permission::ManageRoles->value),
            'timezones' => FormOptions::timezones(),
            'defaultTimezone' => AppSettings::defaultTimezone(),
        ]);
    }

    /**
     * Update an account and its roles.
     */
    public function update(UserRequest $request, User $user, ReminderPlanner $planner): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $previousRoles = $user->getRoleNames()->sort()->values()->all();

            $user->fill($request->safe()->only(['name', 'email', 'timezone', 'phone']));

            if ($user->isDirty('phone')) {
                $user->phone_verified_at = null;
            }

            $user->save();
            $this->audit->logChanges('user.updated', $user, $request->user());

            $user->syncRoles($request->validated('roles'));
            $newRoles = $user->getRoleNames()->sort()->values()->all();

            if ($previousRoles !== $newRoles) {
                $this->audit->log('user.roles_changed', $user, ['roles' => $previousRoles], ['roles' => $newRoles], $request->user());
            }

            if ($request->has('permissions')) {
                $previousPermissions = $user->getDirectPermissions()->pluck('name')->sort()->values()->all();
                $user->syncPermissions($request->validated('permissions', []));
                $newPermissions = $user->getDirectPermissions()->pluck('name')->sort()->values()->all();

                if ($previousPermissions !== $newPermissions) {
                    $this->audit->log('user.permissions_changed', $user, ['permissions' => $previousPermissions], ['permissions' => $newPermissions], $request->user());
                }
            }
        });

        $planner->syncForUser($user->refresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    /**
     * Deactivate or reactivate an account. Deactivation ends the user's sessions.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        DB::transaction(function () use ($request, $user): void {
            $activate = ! $user->is_active;

            $user->forceFill([
                'is_active' => $activate,
                'deactivated_at' => $activate ? null : now(),
            ])->save();

            if (! $activate) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->forceFill(['remember_token' => Str::random(60)])->save();
            }

            $this->audit->log($activate ? 'user.reactivated' : 'user.deactivated', $user, [], [], $request->user());
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active ? __('Account reactivated.') : __('Account deactivated.'),
        ]);

        return back();
    }

    /**
     * Email the user a password reset link.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        $this->audit->log('user.password_reset_sent', $user, [], [], $request->user());

        Inertia::flash('toast', [
            'type' => $status === Password::RESET_LINK_SENT ? 'success' : 'error',
            'message' => __($status),
        ]);

        return back();
    }

    /**
     * Present a user for the administration screens.
     *
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => $user->timezone,
            'phone' => $user->phone,
            'phone_verified' => $user->phone_verified_at !== null,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'role_labels' => $user->roles->pluck('name')->map(fn (string $name) => RolesAndPermissions::label($name))->values()->all(),
            'permissions' => $user->permissions->pluck('name')->values()->all(),
            'last_login_at' => $user->last_login_at?->toIso8601ZuluString(),
            'created_at' => $user->created_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * Get every role with its label, description, and permissions.
     *
     * @return array<int, array{value: string, label: string, description: string|null, permissions: array<int, string>}>
     */
    private function roleOptions(): array
    {
        return SpatieRole::query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (SpatieRole $role) => [
                'value' => $role->name,
                'label' => RolesAndPermissions::label($role->name),
                'description' => Role::tryFrom($role->name)?->description() ?? $role->getAttribute('description'),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();
    }
}
