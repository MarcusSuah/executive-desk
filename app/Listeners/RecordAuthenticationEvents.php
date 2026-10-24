<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Writes sign-in activity to the audit trail. Credentials are never recorded.
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
}
