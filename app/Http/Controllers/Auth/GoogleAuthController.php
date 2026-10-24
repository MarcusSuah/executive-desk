<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\SignInSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as OAuthUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Sign in with Google for existing accounts.
 *
 * Accounts are never created here: the verified Google email must match an active
 * Executive Desk user that an administrator created. Users with two-factor
 * authentication still complete the two-factor challenge.
 */
class GoogleAuthController extends Controller
{
    /**
     * Determine whether Google sign-in is configured.
     */
    public static function enabled(): bool
    {
        return SignInSettings::googleEnabled();
    }

    /**
     * Send the user to Google.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(self::enabled(), 404);
        SignInSettings::applyGoogleConfig();

        $driver = Socialite::driver('google');

        if ($driver instanceof AbstractProvider && filled(config('services.google.hosted_domain'))) {
            $driver->with(['hd' => config('services.google.hosted_domain')]);
        }

        return $driver->redirect();
    }

    /**
     * Handle Google's response and sign the user in.
     */
    public function callback(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless(self::enabled(), 404);
        SignInSettings::applyGoogleConfig();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->fail('Google sign-in was cancelled or could not be completed. Please try again.');
        }

        if (! $googleUser instanceof OAuthUser) {
            return $this->fail('Google sign-in could not be completed. Please try again.');
        }

        $raw = $googleUser->getRaw();
        $email = Str::lower((string) $googleUser->getEmail());
        $emailVerified = (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false);
        $hostedDomain = config('services.google.hosted_domain');

        if ($email === '' || ! $emailVerified) {
            return $this->fail('Your Google account email address is not verified.');
        }

        if (filled($hostedDomain) && ($raw['hd'] ?? null) !== $hostedDomain) {
            return $this->fail("Use your {$hostedDomain} Google account to sign in.");
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $email)->first();

        if ($user === null) {
            $audit->log('auth.google_rejected', null, [], ['reason' => 'no matching account']);

            return $this->fail('There is no Executive Desk account for this Google address. Contact your administrator.');
        }

        if ($user->google_id !== null && $user->google_id !== $googleUser->getId()) {
            return $this->fail('This Executive Desk account is linked to a different Google account.');
        }

        if (! $user->is_active) {
            return $this->fail('This account has been deactivated. Contact your administrator.');
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $googleUser->getId()])->save();
            $audit->log('auth.google_linked', $user, [], [], $user);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->two_factor_confirmed_at !== null) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * Return to the sign-in page with an error.
     */
    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
