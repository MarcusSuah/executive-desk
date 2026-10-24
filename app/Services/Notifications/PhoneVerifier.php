<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\Sms\SmsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Verifies ownership of a mobile number with a one-time code sent by SMS.
 *
 * Only a hash of the code is kept, in the cache, with an expiry and an attempt limit.
 */
class PhoneVerifier
{
    public function __construct(
        private SmsGateway $sms,
        private AuditLogger $audit,
    ) {}

    /**
     * Send a verification code to the number.
     *
     * @throws ValidationException when SMS cannot be sent.
     */
    public function sendCode(User $user, string $phone): void
    {
        if (! $this->sms->isConfigured()) {
            throw ValidationException::withMessages([
                'phone' => 'SMS is not available because no SMS provider has been configured. Contact your administrator.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $result = $this->sms->send($phone, "Your Executive Desk verification code is {$code}. It expires in ".config('executive-desk.phone_verification.code_ttl_minutes').' minutes.');

        if (! $result->wasSent()) {
            throw ValidationException::withMessages([
                'phone' => 'The verification code could not be sent: '.ErrorSanitizer::clean($result->error),
            ]);
        }

        Cache::put($this->key($user), [
            'phone' => $phone,
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(config('executive-desk.phone_verification.code_ttl_minutes')));

        $this->audit->log('phone.code_sent', $user, [], ['phone' => $this->mask($phone)], $user);
    }

    /**
     * Check a code and mark the number as verified.
     *
     * @throws ValidationException when the code is wrong, expired, or attempts are exhausted.
     */
    public function verify(User $user, string $code): void
    {
        $pending = Cache::get($this->key($user));

        if (! is_array($pending)) {
            throw ValidationException::withMessages(['code' => 'The code has expired. Request a new one.']);
        }

        if ($pending['attempts'] >= config('executive-desk.phone_verification.max_attempts')) {
            Cache::forget($this->key($user));

            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Request a new code.']);
        }

        if (! Hash::check($code, $pending['hash'])) {
            $pending['attempts']++;
            Cache::put($this->key($user), $pending, now()->addMinutes(config('executive-desk.phone_verification.code_ttl_minutes')));

            throw ValidationException::withMessages(['code' => 'The code is incorrect.']);
        }

        Cache::forget($this->key($user));

        $user->forceFill(['phone' => $pending['phone'], 'phone_verified_at' => now()])->save();

        $this->audit->log('phone.verified', $user, [], ['phone' => $this->mask($pending['phone'])], $user);
    }

    /**
     * Get the number awaiting verification, if any.
     */
    public function pendingPhone(User $user): ?string
    {
        $pending = Cache::get($this->key($user));

        return is_array($pending) ? $pending['phone'] : null;
    }

    /**
     * Mask a phone number for logs, e.g. "+63•••••4567".
     */
    public function mask(string $phone): string
    {
        return substr($phone, 0, 3).str_repeat('•', max(0, strlen($phone) - 7)).substr($phone, -4);
    }

    /**
     * Get the cache key for the user's pending verification.
     */
    private function key(User $user): string
    {
        return "phone-verification:{$user->id}";
    }
}
