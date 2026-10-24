<?php

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
