<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\RolesAndPermissions;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->administrator()->create();
});

describe('roles', function () {
    it('lists every role with its permissions', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.roles.index'))
            ->assertInertia(fn ($page) => $page
                ->component('admin/roles')
                ->has('roles', 3)
                ->where('roles.0.name', 'administrator')
                ->where('roles.0.label', 'System Administrator')
                ->has('permissions', 11));
    });

    it('creates a custom role with several permissions', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), [
                'name' => 'Chief of Staff',
                'description' => 'Runs the executive office.',
                'permissions' => ['calendar.manage', 'tasks.manage', 'reports.view'],
            ])
            ->assertSessionHasNoErrors();

        $role = Role::findByName('Chief of Staff');

        expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['calendar.manage', 'reports.view', 'tasks.manage'])
            ->and($role->getAttribute('description'))->toBe('Runs the executive office.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created', 'auditable_id' => $role->id]);
    });

    it('changes the permissions of a built-in role and records the change', function () {
        $role = Role::findByName('executive');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $role), ['permissions' => ['reports.view', 'deliveries.view-all']])
            ->assertSessionHasNoErrors();

        expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['deliveries.view-all', 'reports.view']);

        $entry = AuditLog::where('action', 'role.updated')->sole();
        expect($entry->old_values['permissions'])->toBe(['reports.view'])
            ->and($entry->new_values['permissions'])->toBe(['deliveries.view-all', 'reports.view']);
    });

    it('refuses to rename or delete a built-in role', function () {
        $role = Role::findByName('assistant');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $role), ['name' => 'Helper', 'permissions' => []])
            ->assertSessionHasErrors(['name' => 'Built-in roles cannot be renamed.']);

        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHasErrors('role');

        expect(Role::where('name', 'assistant')->exists())->toBeTrue();
    });

    it('keeps user and role management on the administrator role', function () {
        $role = Role::findByName('administrator');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $role), ['permissions' => ['settings.manage']])
            ->assertSessionHasErrors('permissions');

        expect($this->admin->fresh()->can('users.manage'))->toBeTrue()
            ->and($this->admin->fresh()->can('roles.manage'))->toBeTrue();
    });

    it('deletes a custom role without touching the other roles of its holders', function () {
        $role = Role::create(['name' => 'Travel Desk', 'guard_name' => 'web']);
        $user = User::factory()->assistant()->create();
        $user->assignRole('Travel Desk');

        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertSessionHasNoErrors();

        expect(Role::where('name', 'Travel Desk')->exists())->toBeFalse()
            ->and($user->fresh()->getRoleNames()->all())->toBe(['assistant']);
    });

    it('rejects a role named like a built-in role', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), ['name' => 'Executive Assistant', 'permissions' => []])
            ->assertSessionHasErrors(['name' => 'That name belongs to a built-in role.']);
    });

    it('forbids people without the roles permission', function () {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($assistant)->post(route('admin.roles.store'), ['name' => 'X', 'permissions' => []])->assertForbidden();
    });

    it('lets a custom role grant access to the pages it covers', function () {
        Role::create(['name' => 'Auditor', 'guard_name' => 'web'])->givePermissionTo('audit.view');
        $user = User::factory()->create();
        $user->assignRole('Auditor');

        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    });

    it('does not undo administrators’ changes when setup runs again', function () {
        Role::findByName('executive')->syncPermissions([]);
        Role::findByName('assistant')->revokePermissionTo('reports.view');

        RolesAndPermissions::sync();

        expect(Role::findByName('executive')->permissions)->toHaveCount(0)
            ->and(Role::findByName('assistant')->hasPermissionTo('reports.view'))->toBeFalse();
    });
});

describe('people', function () {
    it('gives one person several roles plus individual permissions', function () {
        Notification::fake();

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Riley Multi',
            'email' => 'riley@example.com',
            'timezone' => 'UTC',
            'roles' => ['executive', 'assistant'],
            'permissions' => ['audit.view'],
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'riley@example.com')->sole();

        expect($user->getRoleNames()->sort()->values()->all())->toBe(['assistant', 'executive'])
            ->and($user->getDirectPermissions()->pluck('name')->all())->toBe(['audit.view'])
            ->and($user->can('calendar.manage'))->toBeTrue()
            ->and($user->can('audit.view'))->toBeTrue()
            ->and($user->can('users.manage'))->toBeFalse();
    });

    it('records individual permission changes and tells the person', function () {
        $user = User::factory()->executive()->create();

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'UTC',
            'roles' => ['executive'],
            'permissions' => ['reports.view', 'deliveries.view-all'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.permissions_changed', 'auditable_id' => $user->id]);
        expect($user->notifications()->sole()->data['body'])->toBe('Permissions: View everyone’s delivery history, View reports');
    });

    it('accepts custom roles on accounts', function () {
        Role::create(['name' => 'Chief of Staff', 'guard_name' => 'web']);
        $user = User::factory()->executive()->create();

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'UTC',
            'roles' => ['executive', 'Chief of Staff'],
        ])->assertSessionHasNoErrors();

        expect($user->fresh()->hasRole('Chief of Staff'))->toBeTrue();
    });

    it('requires at least one role and rejects unknown roles', function () {
        $user = User::factory()->executive()->create();
        $payload = ['name' => $user->name, 'email' => $user->email, 'timezone' => 'UTC'];

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [...$payload, 'roles' => []])
            ->assertSessionHasErrors(['roles' => 'Choose at least one role.']);
        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [...$payload, 'roles' => ['wizard']])
            ->assertSessionHasErrors('roles.0');
    });

    it('lets only people who manage roles grant individual permissions', function () {
        Role::create(['name' => 'People Ops', 'guard_name' => 'web'])->givePermissionTo('users.manage');
        $peopleOps = User::factory()->create();
        $peopleOps->assignRole('People Ops');
        $user = User::factory()->executive()->create();

        $this->actingAs($peopleOps)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'UTC',
            'roles' => ['executive'],
            'permissions' => ['users.manage'],
        ])->assertSessionHasErrors('permissions');

        expect($user->fresh()->can('users.manage'))->toBeFalse();
    });
});
