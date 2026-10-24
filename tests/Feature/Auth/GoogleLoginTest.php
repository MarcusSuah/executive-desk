<?php

use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client',
        'services.google.client_secret' => 'test-secret',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
        'services.google.hosted_domain' => null,
    ]);
});

/**
 * Fake the Google callback with the given identity.
 *
 * @param  array<string, mixed>  $raw
 */
function fakeGoogleUser(string $email, string $id = 'google-123', array $raw = ['email_verified' => true]): void
{
    Socialite::fake('google', GoogleUser::fake(['id' => $id, 'name' => 'Google Person', 'email' => $email])->setRaw($raw));
}

it('redirects to Google when sign-in is configured', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect'))->assertRedirect();
});

it('returns 404 when Google sign-in is not configured', function () {
    config(['services.google.client_id' => null]);

    $this->get(route('auth.google.redirect'))->assertNotFound();
});

it('signs in an existing account and links the Google identity', function () {
    $user = User::factory()->executive()->create(['email' => 'jordan@example.com']);
    fakeGoogleUser('Jordan@Example.com');

    $this->get(route('auth.google.callback'))->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->google_id)->toBe('google-123');
    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
});

it('never creates an account for an unknown Google address', function () {
    fakeGoogleUser('stranger@example.com');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'There is no Executive Desk account for this Google address. Contact your administrator.']);

    $this->assertGuest();
    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

it('rejects an unverified Google email', function () {
    User::factory()->create(['email' => 'jordan@example.com']);
    fakeGoogleUser('jordan@example.com', raw: ['email_verified' => false]);

    $this->get(route('auth.google.callback'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects a deactivated account', function () {
    User::factory()->inactive()->create(['email' => 'jordan@example.com']);
    fakeGoogleUser('jordan@example.com');

    $this->get(route('auth.google.callback'))->assertSessionHasErrors(['email' => 'This account has been deactivated. Contact your administrator.']);
    $this->assertGuest();
});

it('rejects a different Google identity than the one linked to the account', function () {
    User::factory()->create(['email' => 'jordan@example.com', 'google_id' => 'google-original']);
    fakeGoogleUser('jordan@example.com', id: 'google-other');

    $this->get(route('auth.google.callback'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('requires the two-factor challenge when the account uses two-factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'jordan@example.com']);
    fakeGoogleUser('jordan@example.com');

    $this->get(route('auth.google.callback'))->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

it('enforces the configured Google Workspace domain', function () {
    config(['services.google.hosted_domain' => 'company.com']);
    User::factory()->create(['email' => 'jordan@example.com']);
    fakeGoogleUser('jordan@example.com', raw: ['email_verified' => true, 'hd' => 'other.com']);

    $this->get(route('auth.google.callback'))->assertSessionHasErrors(['email' => 'Use your company.com Google account to sign in.']);
});

it('shows the Google button on the sign-in page only when configured', function () {
    $this->get(route('login'))->assertInertia(fn ($page) => $page->component('auth/login')->where('googleEnabled', true));

    config(['services.google.client_id' => null]);

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('googleEnabled', false));
});
