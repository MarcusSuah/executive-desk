<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Presentation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Reminders\ReminderPlanner;
use App\Services\Reports\ReportExporter;
use App\Support\AppSettings;
use App\Support\DisplayTime;
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
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class UserController extends Controller
{
    /**
     * @var array<int, int>
     */
    private const PER_PAGE = [10, 25, 50, 100];

    /**
     * Permissions that let someone work on every executive's records, with the area each covers.
     *
     * @var array<string, string>
     */
    private const RECORD_PERMISSIONS = [
        'calendar.manage' => 'Calendar',
        'tasks.manage' => 'Tasks',
        'presentations.manage' => 'Presentations',
        'briefings.manage' => 'Briefings',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * List user accounts with account statistics.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $this->filters($request);

        $users = $this->query($filters)
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'stats' => [
                'total' => User::query()->count(),
                'pending' => User::query()->where('is_active', true)->whereNull('last_login_at')->count(),
                'active' => User::query()->where('is_active', true)->whereNotNull('last_login_at')->count(),
                'suspended' => User::query()->where('is_active', false)->count(),
            ],
            'filters' => [
                'q' => $filters['q'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'per_page' => $filters['per_page'],
            ],
            'roles' => $this->roleOptions(),
            'perPageOptions' => self::PER_PAGE,
        ]);
    }

    /**
     * Export the filtered accounts as CSV or Excel.
     */
    public function export(Request $request, ReportExporter $exporter): HttpResponse
    {
        Gate::authorize('viewAny', User::class);

        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx'])]])['format'];
        $filters = $this->filters($request);
        $viewer = $request->user();

        $rows = $this->query($filters)->orderBy('name')->get()->map(function (User $user) use ($viewer): array {
            $scope = $this->scope($user);

            return [
                'name' => $user->name,
                'job_title' => (string) $user->job_title,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->map(fn (string $name) => RolesAndPermissions::label($name))->implode(', '),
                'scope' => "{$scope['label']} ({$scope['detail']})",
                'status' => $this->statusLabel($user),
                'last_login' => $user->last_login_at ? DisplayTime::forUser($user->last_login_at->toImmutable(), $viewer) : 'Never',
                'timezone' => $user->timezone,
            ];
        })->all();

        $this->audit->log('export.users', null, [], [
            'format' => $format,
            'filters' => array_filter(array_diff_key($filters, ['per_page' => true])),
            'rows' => count($rows),
        ], $viewer);

        return $exporter->export([
            'title' => 'Users',
            'columns' => [
                'name' => 'Name',
                'job_title' => 'Job title',
                'email' => 'Email',
                'roles' => 'Roles',
                'scope' => 'Scope',
                'status' => 'Status',
                'last_login' => 'Last sign-in',
                'timezone' => 'Time zone',
            ],
            'rows' => $rows,
            'summary' => ['Users' => count($rows)],
        ], $format, 'Exported '.DisplayTime::forUser(now(), $viewer), $viewer->timezone);
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
                ...$request->safe()->only(['name', 'job_title', 'email', 'timezone', 'phone']),
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

            $user->fill($request->safe()->only(['name', 'job_title', 'email', 'timezone', 'phone']));

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
     * Permanently remove an account. Accounts that own appointments or presentations
     * must be suspended instead, because those records need their executive.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        if ($user->ownedEvents()->withTrashed()->exists() || Presentation::withTrashed()->where('owner_id', $user->id)->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':name owns appointments or presentations, so the account cannot be removed. Suspend it instead.', ['name' => $user->name]),
            ]);

            return back();
        }

        DB::transaction(function () use ($request, $user): void {
            $this->audit->log('user.deleted', $user, ['name' => $user->name, 'email' => $user->email], [], $request->user());

            $user->notifications()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User “:name” removed.', ['name' => $user->name])]);

        return to_route('admin.users.index');
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
            'job_title' => $user->job_title,
            'email' => $user->email,
            'timezone' => $user->timezone,
            'phone' => $user->phone,
            'phone_verified' => $user->phone_verified_at !== null,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'role_labels' => $user->roles->pluck('name')->map(fn (string $name) => RolesAndPermissions::label($name))->values()->all(),
            'permissions' => $user->permissions->pluck('name')->values()->all(),
            'status' => $this->status($user),
            'scope' => $this->scope($user),
            'last_login_at' => $user->last_login_at?->toIso8601ZuluString(),
            'created_at' => $user->created_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * Validate the list filters.
     *
     * @return array{q?: string, role?: string, status?: string, per_page: int}
     */
    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            // "inactive" is the older name for "suspended".
            'status' => ['nullable', Rule::in(['active', 'pending', 'suspended', 'inactive'])],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE)],
        ]);

        $normalized = ['per_page' => (int) ($filters['per_page'] ?? 10)];

        foreach (['q', 'role', 'status'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $normalized[$key] = $key === 'status' && $filters[$key] === 'inactive' ? 'suspended' : (string) $filters[$key];
            }
        }

        return $normalized;
    }

    /**
     * Build the filtered account query.
     *
     * @param  array{q?: string, role?: string, status?: string, per_page: int}  $filters
     * @return Builder<User>
     */
    private function query(array $filters): Builder
    {
        return User::query()
            ->with(['roles:id,name', 'roles.permissions:id,name', 'permissions:id,name'])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('job_title', 'like', $like));
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match ($status) {
                'active' => $query->where('is_active', true)->whereNotNull('last_login_at'),
                'pending' => $query->where('is_active', true)->whereNull('last_login_at'),
                default => $query->where('is_active', false),
            });
    }

    /**
     * Get the account status: "active", "pending" (never signed in), or "suspended".
     */
    private function status(User $user): string
    {
        return match (true) {
            ! $user->is_active => 'suspended',
            $user->last_login_at === null => 'pending',
            default => 'active',
        };
    }

    /**
     * Get a readable account status.
     */
    private function statusLabel(User $user): string
    {
        return match ($this->status($user)) {
            'suspended' => 'Suspended',
            'pending' => 'Pending invite',
            default => 'Active',
        };
    }

    /**
     * Describe whose records the person can work with, from their roles and individual permissions.
     *
     * @return array{label: string, detail: string}
     */
    private function scope(User $user): array
    {
        $permissions = $user->permissions->pluck('name')
            ->merge($user->roles->pluck('permissions')->flatten()->pluck('name'))
            ->filter(fn ($name) => is_string($name))
            ->unique();

        $areas = array_values(array_intersect_key(self::RECORD_PERMISSIONS, array_flip($permissions->all())));
        $administers = $permissions->contains(fn (string $name) => in_array($name, [Permission::ManageUsers->value, Permission::ManageRoles->value, Permission::ManageSettings->value], true));

        if ($areas === []) {
            return $administers
                ? ['label' => 'Administration', 'detail' => 'Users, roles, and settings']
                : ['label' => 'Own records', 'detail' => 'Items they own or attend'];
        }

        return [
            'label' => 'All executives',
            'detail' => count($areas) === count(self::RECORD_PERMISSIONS)
                ? ($administers ? 'All areas and administration' : 'All areas')
                : implode(', ', $areas).($administers ? ' and administration' : ''),
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
