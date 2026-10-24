<?php

use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\User;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

beforeEach(function () {
    $this->user = User::factory()->executive()->create();
});

it('records profile changes', function () {
    $this->actingAs($this->user)->patch(route('profile.update'), [
        'name' => 'New Name',
        'email' => $this->user->email,
        'timezone' => 'Asia/Manila',
    ]);

    $entry = AuditLog::where('action', 'profile.updated')->sole();

    expect($entry->new_values)->toMatchArray(['name' => 'New Name', 'timezone' => 'Asia/Manila'])
        ->and($entry->user_id)->toBe($this->user->id);
});

it('records a password change without storing the password', function () {
    $this->actingAs($this->user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    expect(AuditLog::where('action', 'security.password_changed')->exists())->toBeTrue()
        ->and(AuditLog::query()->get()->toJson())->not->toContain('a-brand-new-password');
});

it('records two-factor changes', function () {
    event(new TwoFactorAuthenticationEnabled($this->user));

    expect(AuditLog::where('action', 'security.two_factor_enabled')->where('user_id', $this->user->id)->exists())->toBeTrue();
});

it('records a change request the application did not describe, with field names but not values', function () {
    $this->actingAs($this->user)->post(route('inbox.read-all'), ['note' => 'private value']);

    $entry = AuditLog::where('action', 'request.inbox.read-all')->sole();

    expect($entry->new_values)->toMatchArray(['method' => 'POST', 'outcome' => 'succeeded', 'fields' => ['note']])
        ->and(json_encode($entry->new_values))->not->toContain('private value');
});

it('records refused and invalid attempts', function () {
    $event = CalendarEvent::factory()->create();

    $this->actingAs($this->user)->post(route('events.cancel', $event))->assertForbidden();
    $this->actingAs($this->user)->post(route('phone.verify'), ['code' => 'x']);

    expect(AuditLog::where('action', 'request.events.cancel')->sole()->new_values)->toMatchArray(['outcome' => 'denied', 'records' => ['event' => $event->id]])
        ->and(AuditLog::where('action', 'request.phone.verify')->sole()->new_values['outcome'])->toBe('rejected by validation');
});

it('does not add a generic entry when the action was already audited', function () {
    $event = CalendarEvent::factory()->for($this->user, 'owner')->create();
    $assistant = User::factory()->assistant()->create();

    $this->actingAs($assistant)->post(route('events.cancel', $event));

    expect(AuditLog::where('action', 'event.cancelled')->count())->toBe(1)
        ->and(AuditLog::where('action', 'like', 'request.%')->count())->toBe(0);
});

it('records page views but not background refreshes', function () {
    $this->actingAs($this->user)->get(route('calendar'));
    $this->actingAs($this->user)->get(route('dashboard'), ['X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'inbox', 'X-Inertia-Partial-Component' => 'dashboard']);

    expect(AuditLog::where('action', 'page.viewed')->pluck('new_values')->pluck('route')->all())->toBe(['calendar']);
});

it('does not record page views when turned off', function () {
    config(['executive-desk.audit.page_views' => false]);

    $this->actingAs($this->user)->get(route('calendar'));

    expect(AuditLog::where('action', 'page.viewed')->exists())->toBeFalse();
});

it('records guest password reset requests', function () {
    $this->post(route('password.email'), ['email' => $this->user->email]);

    expect(AuditLog::where('action', 'auth.password_reset_link_sent')->where('auditable_id', $this->user->id)->exists())->toBeTrue();
});

it('prunes entries older than the retention period only when one is set', function () {
    AuditLog::factory()->create(['created_at' => now()->subDays(400)]);
    AuditLog::factory()->create(['created_at' => now()->subDays(10)]);

    $this->artisan('audit:prune')->assertSuccessful();
    expect(AuditLog::count())->toBe(2);

    $this->artisan('audit:prune', ['--days' => 365])->assertSuccessful();
    expect(AuditLog::count())->toBe(1);
});

it('refuses to delete an account that owns appointments, without recording a deletion', function () {
    CalendarEvent::factory()->for($this->user, 'owner')->create();

    $this->actingAs($this->user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect($this->user->fresh())->not->toBeNull()
        ->and(AuditLog::where('action', 'user.deleted')->exists())->toBeFalse();
});
