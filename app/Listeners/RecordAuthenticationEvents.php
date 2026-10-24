<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Events\Verified;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Writes sign-in and account security activity to the audit trail.
 * Credentials, codes, and secrets are never recorded.
 */
class RecordAuthenticationEvents
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Record a successful login.
     */
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit->log('auth.login', $event->user, actor: $event->user);
    }

    /**
     * Record a logout.
     */
    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.logout', $event->user, actor: $event->user);
        }
    }

    /**
     * Record a failed login against an existing account.
     */
    public function handleFailed(Failed $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.failed', $event->user, actor: null);
        }
    }

    /**
     * Record a completed password reset.
     */
    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.password_reset', $event->user, actor: $event->user);
        }
    }

    /**
     * Record a request for a password reset link.
     */
    public function handlePasswordResetLinkSent(PasswordResetLinkSent $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.password_reset_link_sent', $event->user, actor: null);
        }
    }

    /**
     * Record an email address verification.
     */
    public function handleVerified(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.email_verified', $event->user, actor: $event->user);
        }
    }

    /**
     * Record a login lockout after too many attempts.
     */
    public function handleLockout(Lockout $event): void
    {
        $this->audit->log('auth.lockout', null, [], ['email' => (string) $event->request->input('email')]);
    }

    /**
     * Record two-factor authentication being turned on (before confirmation).
     */
    public function handleTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->recordSecurity('security.two_factor_enabled', $event->user);
    }

    /**
     * Record two-factor authentication being confirmed.
     */
    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->recordSecurity('security.two_factor_confirmed', $event->user);
    }

    /**
     * Record two-factor authentication being turned off.
     */
    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->recordSecurity('security.two_factor_disabled', $event->user);
    }

    /**
     * Record new recovery codes being generated.
     */
    public function handleRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->recordSecurity('security.recovery_codes_generated', $event->user);
    }

    /**
     * Record a recovery code being used to sign in.
     */
    public function handleRecoveryCodeReplaced(RecoveryCodeReplaced $event): void
    {
        $this->recordSecurity('auth.recovery_code_used', $event->user);
    }

    /**
     * Record a failed two-factor code.
     */
    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->audit->log('auth.two_factor_failed', $event->user, actor: null);
    }

    /**
     * Record a security change made by the user to their own account.
     */
    private function recordSecurity(string $action, mixed $user): void
    {
        if ($user instanceof User) {
            $this->audit->log($action, $user, actor: $user);
        }
    }
}
