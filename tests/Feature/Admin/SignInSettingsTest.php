<?php

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\SignInSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Socialite;

beforeEach(function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    $this->admin = User::factory()->administrator()->create();
});

describe('Google sign-in settings', function () {
    it('turns on Google sign-in from the settings page and shows the button', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.sign-in'), [
                'google_client_id' => '1234-abc.apps.googleusercontent.com',
                'google_client_secret' => 'super-secret-value',
                'google_hosted_domain' => 'company.com',
            ])
            ->assertSessionHasNoErrors();

        expect(SignInSettings::googleEnabled())->toBeTrue()
            ->and(SignInSettings::googleClientSecret())->toBe('super-secret-value')
            ->and(AppSetting::where('key', SignInSettings::GOOGLE_CLIENT_SECRET)->sole()->value)->not->toBe('super-secret-value')
            ->and(AuditLog::where('action', 'settings.google_sign_in_updated')->sole()->new_values)->not->toContain('super-secret-value');

        auth()->logout();

        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('googleEnabled', true));
    });

    it('uses the saved credentials when redirecting to Google', function () {
        SignInSettings::saveGoogle('1234-abc.apps.googleusercontent.com', 'secret', null);
        Socialite::fake('google');

        $this->get(route('auth.google.redirect'))->assertRedirect();

        expect(config('services.google.client_id'))->toBe('1234-abc.apps.googleusercontent.com')
            ->and(config('services.google.redirect'))->toBe(route('auth.google.callback'));
    });

    it('keeps the saved secret when the field is left blank', function () {
        SignInSettings::saveGoogle('1234-abc.apps.googleusercontent.com', 'original-secret', null);

        $this->actingAs($this->admin)
            ->put(route('admin.settings.sign-in'), ['google_client_id' => '5678-xyz.apps.googleusercontent.com', 'google_client_secret' => ''])
            ->assertSessionHasNoErrors();

        expect(SignInSettings::googleClientSecret())->toBe('original-secret')
            ->and(SignInSettings::googleClientId())->toBe('5678-xyz.apps.googleusercontent.com');
    });

    it('requires a secret the first time a client ID is saved', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.sign-in'), ['google_client_id' => '1234-abc.apps.googleusercontent.com'])
            ->assertSessionHasErrors(['google_client_secret' => 'Enter the client secret.']);
    });

    it('removes the saved Google settings', function () {
        SignInSettings::saveGoogle('1234-abc.apps.googleusercontent.com', 'secret', null);

        $this->actingAs($this->admin)->put(route('admin.settings.sign-in'), ['clear' => true]);

        expect(SignInSettings::googleEnabled())->toBeFalse();
    });

    it('forbids assistants from changing sign-in settings', function () {
        $this->actingAs(User::factory()->assistant()->create())
            ->put(route('admin.settings.sign-in'), ['google_client_id' => 'x', 'google_client_secret' => 'y'])
            ->assertForbidden();
    });
});

describe('sign-in background', function () {
    beforeEach(fn () => Storage::fake('local'));

    it('uploads a background that the sign-in page then uses', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.login-background'), [
                'background' => UploadedFile::fake()->image('office.jpg', 1600, 1000),
            ])
            ->assertSessionHasNoErrors();

        $path = SignInSettings::backgroundPath();
        Storage::disk('local')->assertExists($path);

        auth()->logout();

        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('heroImage', SignInSettings::backgroundUrl()));
        $this->get(route('branding.login-background'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('replaces the previous background file', function () {
        $this->actingAs($this->admin)->post(route('admin.settings.login-background'), ['background' => UploadedFile::fake()->image('first.jpg', 1600, 1000)]);
        $first = SignInSettings::backgroundPath();

        $this->actingAs($this->admin)->post(route('admin.settings.login-background'), ['background' => UploadedFile::fake()->image('second.png', 1600, 1000)]);

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists(SignInSettings::backgroundPath());
    });

    it('rejects files that are not images or are too small', function (UploadedFile $file) {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.login-background'), ['background' => $file])
            ->assertSessionHasErrors('background');
    })->with([
        'not an image' => fn () => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        'too small' => fn () => UploadedFile::fake()->image('tiny.jpg', 300, 200),
    ]);

    it('resets to the default background', function () {
        $this->actingAs($this->admin)->post(route('admin.settings.login-background'), ['background' => UploadedFile::fake()->image('office.jpg', 1600, 1000)]);
        $path = SignInSettings::backgroundPath();

        $this->actingAs($this->admin)->delete(route('admin.settings.login-background.destroy'));

        Storage::disk('local')->assertMissing($path);
        expect(SignInSettings::backgroundPath())->toBeNull();
        $this->get(route('branding.login-background'))->assertNotFound();
    });
});
