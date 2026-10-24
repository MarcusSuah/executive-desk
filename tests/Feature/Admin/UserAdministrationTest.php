<?php

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->administrator()->create();
});

/**
 * Build a valid user payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userPayload(array $overrides = []): array
{
    return [
        'name' => 'Jordan Reyes',
        'email' => 'jordan@example.com',
        'timezone' => 'Asia/Manila',
        'phone' => '+63 917 123 4567',
        'roles' => ['executive'],
        ...$overrides,
    ];
}

it('creates an account and emails a link to set the password', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), userPayload())
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'jordan@example.com')->sole();

    expect($user->hasRole('executive'))->toBeTrue()
        ->and($user->hasRole('administrator'))->toBeFalse()
        ->and($user->phone)->toBe('+639171234567')
        ->and($user->timezone)->toBe('Asia/Manila');

    Notification::assertSentTo($user, ResetPassword::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'auditable_id' => $user->id]);
});

it('rejects a phone number that is not in international format', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), userPayload(['phone' => '0917 123 4567']))
        ->assertSessionHasErrors(['phone' => 'Enter the phone number in international format, e.g. +639171234567.']);
});

it('forbids assistants and executives from managing users', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.store'), userPayload())->assertForbidden();
})->with(['assistant', 'executive']);

it('prevents administrators from removing their own administrator role', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->admin), userPayload([
            'email' => $this->admin->email,
            'roles' => ['assistant'],
        ]))
        ->assertSessionHasErrors(['roles' => 'You cannot remove your own administrator role.']);
});

it('records role changes in the audit log', function () {
    $user = User::factory()->executive()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), userPayload(['email' => $user->email, 'roles' => ['executive', 'assistant']]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles_changed', 'auditable_id' => $user->id]);
});

it('deactivates an account and blocks its next login', function () {
    $user = User::factory()->executive()->create();

    $this->actingAs($this->admin)->patch(route('admin.users.active', $user))->assertRedirect();

    expect($user->refresh()->is_active)->toBeFalse();

    auth()->logout();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account has been deactivated. Contact your administrator.']);
    $this->assertGuest();
});

it('signs out a deactivated user on their next request', function () {
    $user = User::factory()->executive()->inactive()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('prevents administrators from deactivating themselves', function () {
    $this->actingAs($this->admin)->patch(route('admin.users.active', $this->admin))->assertForbidden();
});

it('records successful logins in the audit log', function () {
    $user = User::factory()->executive()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->refresh()->last_login_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    $this->assertDatabaseMissing('audit_logs', ['new_values' => json_encode(['password' => 'password'])]);
});

describe('user list', function () {
    beforeEach(function () {
        $this->admin->forceFill(['last_login_at' => now()])->save();
        $this->invited = User::factory()->executive()->create(['name' => 'Ina Invited', 'job_title' => 'Chief Executive Officer', 'last_login_at' => null]);
        $this->signedIn = User::factory()->assistant()->create(['name' => 'Sam Signed', 'last_login_at' => now()->subDay()]);
        $this->suspended = User::factory()->executive()->inactive()->create(['name' => 'Sue Suspended']);
    });

    it('counts accounts by status and describes each person’s scope', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/index')
                ->where('stats', ['total' => 4, 'pending' => 1, 'active' => 2, 'suspended' => 1])
                ->where('users.per_page', 10)
                ->where('users.data', function ($users): bool {
                    $byName = collect($users)->keyBy('name');

                    return $byName['Ina Invited']['status'] === 'pending'
                        && $byName['Ina Invited']['job_title'] === 'Chief Executive Officer'
                        && $byName['Ina Invited']['scope']['label'] === 'Own records'
                        && $byName['Sam Signed']['status'] === 'active'
                        && $byName['Sam Signed']['scope']['label'] === 'All executives'
                        && $byName['Sue Suspended']['status'] === 'suspended';
                }));
    });

    it('filters by status, job title, and page size', function (array $query, array $names) {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index', $query))
            ->assertInertia(fn ($page) => $page->where('users.data', fn ($users) => collect($users)->pluck('name')->all() === $names));
    })->with([
        'pending invites' => [['status' => 'pending'], ['Ina Invited']],
        'suspended' => [['status' => 'suspended'], ['Sue Suspended']],
        'older "inactive" name' => [['status' => 'inactive'], ['Sue Suspended']],
        'job title search' => [['q' => 'Chief Exec'], ['Ina Invited']],
    ]);

    it('rejects an unsupported page size', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['per_page' => 7]))
            ->assertSessionHasErrors('per_page');
    });

    it('exports the filtered accounts', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.export', ['format' => 'csv', 'status' => 'pending']))
            ->assertOk()
            ->assertDownload();

        $csv = file_get_contents($response->baseResponse->getFile()->getPathname());

        expect($csv)->toContain('Ina Invited')->toContain('Pending invite')->not->toContain('Sam Signed');
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.users', 'user_id' => $this->admin->id]);
    });
});

it('saves a job title', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), userPayload(['job_title' => 'Managing Director']))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'jordan@example.com')->sole()->job_title)->toBe('Managing Director');
});

describe('removing a user', function () {
    it('removes an account and records who did it', function () {
        $user = User::factory()->assistant()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deleted', 'user_id' => $this->admin->id, 'auditable_id' => $user->id]);
    });

    it('keeps an account that owns appointments', function () {
        $executive = User::factory()->executive()->create();
        CalendarEvent::factory()->for($executive, 'owner')->create();

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $executive));

        $this->assertModelExists($executive);
    });

    it('does not let administrators remove themselves or assistants remove anyone', function () {
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertForbidden();

        $assistant = User::factory()->assistant()->create();
        $this->actingAs($assistant)->delete(route('admin.users.destroy', User::factory()->create()))->assertForbidden();
    });
});
