<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\InAppNotice;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds items to a user's in-app notification bell.
 *
 * Each item's ID is derived from a caller-supplied key, so the same reminder,
 * schedule change, or briefing appears once even when it is delivered on several
 * channels or the job is retried.
 */
class InAppNotifier
{
    /**
     * Add a notification for the user unless one with the same key already exists.
     *
     * @param  string|null  $url  An absolute application URL or a relative path; stored as a path.
     */
    public function notify(User $user, string $key, string $kind, string $title, string $body, ?string $url = null): void
    {
        $notice = new InAppNotice($kind, $title, $body, $this->toPath($url));
        $notice->id = $this->idFor($user, $key);

        try {
            $user->notify($notice);
        } catch (UniqueConstraintViolationException) {
            // Already shown for this key.
        }
    }

    /**
     * Build a deterministic UUID-formatted ID from the user and key.
     */
    private function idFor(User $user, string $key): string
    {
        $hash = sha1("{$user->id}|{$key}");

        return sprintf(
            '%s-%s-5%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    /**
     * Keep only the path and query, so a stored link can never point off-site.
     */
    private function toPath(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);
        $path = '/'.ltrim($parts['path'] ?? '/', '/');

        return isset($parts['query']) ? "{$path}?{$parts['query']}" : $path;
    }
}
