<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\DigestItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Applies a person's quiet hours and digest preferences to an email, SMS, or push alert
 * that their category settings already allow.
 *
 * - Emails for digest categories (below high priority, or low only) wait for the digest.
 * - During quiet hours, alerts are held back unless high priority alerts may break
 *   through; a held email still reaches the digest when one is set up.
 *
 * The in-app bell is never affected.
 */
class AlertGate
{
    public const SEND = 'send';

    public const DIGESTED = 'digested';

    public const QUIET_HOURS = 'quiet_hours';

    /**
     * Decide what happens to the alert, keeping it for the digest when that is the outcome.
     *
     * @return self::SEND|self::DIGESTED|self::QUIET_HOURS
     */
    public function admit(User $user, string $category, NotificationChannel $channel, string $title, string $body, ?string $url, ?CarbonImmutable $now = null): string
    {
        $preferences = $user->preferences();
        $isMail = $channel === NotificationChannel::Mail;

        if ($isMail && $preferences->emailsViaDigest($category)) {
            $this->keep($user, $category, $title, $body, $url);

            return self::DIGESTED;
        }

        if (! $preferences->silencedByQuietHours($category, $now ?? now(), $user->timezone)) {
            return self::SEND;
        }

        if ($isMail && $preferences->digest_frequency !== 'off') {
            $this->keep($user, $category, $title, $body, $url);

            return self::DIGESTED;
        }

        return self::QUIET_HOURS;
    }

    /**
     * Store the alert for the next digest.
     */
    private function keep(User $user, string $category, string $title, string $body, ?string $url): void
    {
        $item = new DigestItem([
            'category' => $category,
            'title' => Str::limit($title, 195),
            'body' => Str::limit($body, 495),
            'url' => $url !== null && strlen($url) <= 500 ? $url : null,
        ]);
        $item->user_id = $user->id;
        $item->save();
    }
}
