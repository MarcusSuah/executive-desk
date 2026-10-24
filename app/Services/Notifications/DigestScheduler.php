<?php

namespace App\Services\Notifications;

use App\Jobs\SendDigest;
use App\Models\NotificationPreference;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Queues each person's email digest once their chosen local time has arrived.
 *
 * A digest period is claimed with a conditional update of last_digest_at, so running
 * the scheduler twice (or on two servers) never sends the same digest twice.
 */
class DigestScheduler
{
    /**
     * Run one scheduling cycle and return the number of digests queued.
     */
    public function run(?CarbonImmutable $now = null): int
    {
        $now ??= now();
        $queued = 0;

        User::query()
            ->active()
            ->whereHas('notificationPreference', fn ($query) => $query->where('digest_frequency', '!=', 'off'))
            ->whereHas('digestItems', fn ($query) => $query->whereNull('digested_at'))
            ->with('notificationPreference')
            ->each(function (User $user) use ($now, &$queued): void {
                $scheduled = $this->scheduledFor($user, $now);

                if ($scheduled !== null && $this->claim($user->preferences(), $scheduled, $now)) {
                    SendDigest::dispatch($user->id);
                    $queued++;
                }
            });

        return $queued;
    }

    /**
     * Get the start of the current digest period, or null when no digest is due yet.
     */
    public function scheduledFor(User $user, CarbonImmutable $now): ?CarbonImmutable
    {
        $preferences = $user->preferences();
        $local = $now->setTimezone($user->timezone);

        if ($preferences->digest_frequency === 'weekly' && $local->dayOfWeekIso !== $preferences->digest_day) {
            return null;
        }

        $scheduled = CarbonImmutable::parse($local->toDateString().' '.$preferences->digest_time, $user->timezone);

        return $scheduled->lessThanOrEqualTo($local) ? $scheduled->utc() : null;
    }

    /**
     * Mark the period as sent unless another run already did.
     */
    private function claim(NotificationPreference $preferences, CarbonImmutable $scheduled, CarbonImmutable $now): bool
    {
        return NotificationPreference::query()
            ->whereKey($preferences->id)
            ->where(fn ($query) => $query->whereNull('last_digest_at')->orWhere('last_digest_at', '<', $scheduled))
            ->update(['last_digest_at' => $now, 'updated_at' => now()]) === 1;
    }
}
