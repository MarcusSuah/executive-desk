<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Sign-in page options that administrators manage in the app: Google sign-in
 * credentials and the sign-in background image.
 *
 * Values saved in the app take precedence over the GOOGLE_* environment variables.
 * The client secret is stored encrypted with the application key.
 */
class SignInSettings
{
    public const GOOGLE_CLIENT_ID = 'sign_in.google_client_id';

    public const GOOGLE_CLIENT_SECRET = 'sign_in.google_client_secret';

    public const GOOGLE_HOSTED_DOMAIN = 'sign_in.google_hosted_domain';

    public const BACKGROUND_PATH = 'sign_in.background_path';

    /**
     * The private disk directory for the sign-in background.
     */
    public const BACKGROUND_DIRECTORY = 'branding';

    /**
     * Get the effective Google client ID.
     */
    public static function googleClientId(): ?string
    {
        return self::filledOrNull(AppSettings::get(self::GOOGLE_CLIENT_ID)) ?? self::filledOrNull(config('services.google.client_id'));
    }

    /**
     * Get the effective Google client secret.
     */
    public static function googleClientSecret(): ?string
    {
        $stored = AppSettings::get(self::GOOGLE_CLIENT_SECRET);

        if (is_string($stored) && $stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (DecryptException) {
                // The application key changed; fall back to the environment.
            }
        }

        return self::filledOrNull(config('services.google.client_secret'));
    }

    /**
     * Get the Google Workspace domain sign-in is restricted to, if any.
     */
    public static function googleHostedDomain(): ?string
    {
        return self::filledOrNull(AppSettings::get(self::GOOGLE_HOSTED_DOMAIN)) ?? self::filledOrNull(config('services.google.hosted_domain'));
    }

    /**
     * Determine whether a client secret has been saved in the app.
     */
    public static function hasStoredGoogleSecret(): bool
    {
        return filled(AppSettings::get(self::GOOGLE_CLIENT_SECRET));
    }

    /**
     * Get the callback URL to register in the Google Cloud console.
     */
    public static function googleRedirectUri(): string
    {
        return route('auth.google.callback');
    }

    /**
     * Determine whether Google sign-in is fully configured.
     */
    public static function googleEnabled(): bool
    {
        return self::googleClientId() !== null && self::googleClientSecret() !== null;
    }

    /**
     * Apply the effective Google settings to the Socialite configuration.
     */
    public static function applyGoogleConfig(): void
    {
        config([
            'services.google.client_id' => self::googleClientId(),
            'services.google.client_secret' => self::googleClientSecret(),
            'services.google.redirect' => self::googleRedirectUri(),
            'services.google.hosted_domain' => self::googleHostedDomain(),
        ]);
    }

    /**
     * Save Google settings. A blank secret keeps the stored one.
     */
    public static function saveGoogle(?string $clientId, ?string $clientSecret, ?string $hostedDomain): void
    {
        AppSettings::set(self::GOOGLE_CLIENT_ID, self::filledOrNull($clientId) ?? '');
        AppSettings::set(self::GOOGLE_HOSTED_DOMAIN, self::filledOrNull($hostedDomain) ?? '');

        if (filled($clientSecret)) {
            AppSettings::set(self::GOOGLE_CLIENT_SECRET, Crypt::encryptString((string) $clientSecret));
        }
    }

    /**
     * Remove the Google settings saved in the app.
     */
    public static function clearGoogle(): void
    {
        AppSettings::set(self::GOOGLE_CLIENT_ID, '');
        AppSettings::set(self::GOOGLE_CLIENT_SECRET, '');
        AppSettings::set(self::GOOGLE_HOSTED_DOMAIN, '');
    }

    /**
     * Get the stored background path on the private disk, if the file exists.
     */
    public static function backgroundPath(): ?string
    {
        $path = AppSettings::get(self::BACKGROUND_PATH);

        return is_string($path) && $path !== '' && Storage::disk('local')->exists($path) ? $path : null;
    }

    /**
     * Get the public URL of the sign-in background, or null for the default gradient.
     */
    public static function backgroundUrl(): ?string
    {
        $path = self::backgroundPath();

        if ($path !== null) {
            return route('branding.login-background', ['v' => substr(md5($path), 0, 10)]);
        }

        return file_exists(public_path('images/login-hero.jpg')) ? '/images/login-hero.jpg' : null;
    }

    /**
     * Return the value when it is a non-empty string.
     */
    private static function filledOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
