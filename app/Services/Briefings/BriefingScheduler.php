<?php

namespace App\Services\Briefings;

use App\Enums\BriefingStatus;
use App\Enums\BriefingType;
use App\Jobs\SendBriefing;
use App\Models\ExecutiveBriefing;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Generates and queues briefings once each user's chosen local time has arrived.
 */
class BriefingScheduler
{
    /**
     * How long after the chosen time a missed briefing is still sent.
     */
    private const SEND_WINDOW_MINUTES = 240;

    public function __construct(private BriefingGenerator $generator) {}

    /**
     * Run one scheduling cycle and return the number of briefings queued.
     */
    public function run(?CarbonImmutable $now = null): int
    {
        $now ??= now();
        $queued = 0;

        User::query()
            ->active()
            ->whereHas('notificationPreference', fn ($query) => $query
                ->where('daily_briefing_enabled', true)
                ->orWhere('weekly_briefing_enabled', true))
            ->with('notificationPreference')
            ->each(function (User $user) use ($now, &$queued): void {
                foreach (BriefingType::cases() as $type) {
                    if ($this->isDue($user, $type, $now) && $this->queue($user, $type, $now)) {
                        $queued++;
                    }
                }
            });

        return $queued;
    }

    /**
     * Determine whether the user's briefing of this type is due now.
     */
    public function isDue(User $user, BriefingType $type, CarbonImmutable $now): bool
    {
        $preferences = $user->preferences();
        $local = $now->setTimezone($user->timezone);

        [$enabled, $time] = $type === BriefingType::Daily
            ? [$preferences->daily_briefing_enabled, $preferences->daily_briefing_time]
            : [$preferences->weekly_briefing_enabled, $preferences->weekly_briefing_time];

        if (! $enabled) {
            return false;
        }

        if ($type === BriefingType::Weekly && $local->dayOfWeekIso !== $preferences->weekly_briefing_day) {
            return false;
        }

        $scheduled = CarbonImmutable::parse($local->toDateString().' '.$time, $user->timezone);
        $minutesLate = $scheduled->diffInMinutes($local, false);

        return $minutesLate >= 0 && $minutesLate <= self::SEND_WINDOW_MINUTES;
    }

    /**
     * Generate the briefing for the current period and queue its delivery once.
     */
    private function queue(User $user, BriefingType $type, CarbonImmutable $now): bool
    {
        $briefing = $this->generator->generate($user, $type, $now->setTimezone($user->timezone));

        $claimed = ExecutiveBriefing::query()
            ->whereKey($briefing->id)
            ->where('status', BriefingStatus::Generated->value)
            ->update(['status' => BriefingStatus::Delivering->value, 'updated_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        // Refresh the content right before sending so it reflects the latest schedule.
        $this->generator->generate($user, $type, $now->setTimezone($user->timezone), refresh: true);

        SendBriefing::dispatch($briefing->id);

        return true;
    }
}
