<?php

namespace App\Services\Calendar;

use App\Jobs\SendScheduleChangeNotice;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Tells the owner and internal attendees (other than the person making the change)
 * that an appointment moved, changed location, or was cancelled.
 */
class ScheduleChangeNotifier
{
    /**
     * Queue change notices after the surrounding transaction commits.
     *
     * @param  'rescheduled'|'updated'|'cancelled'  $change
     * @param  array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, location: string|null}  $previous
     */
    public function notify(CalendarEvent $event, User $actor, string $change, array $previous): void
    {
        $recipients = User::query()
            ->active()
            ->whereIn('id', $event->recipientIds())
            ->whereKeyNot($actor->id)
            ->with('notificationPreference')
            ->get();

        foreach ($recipients as $recipient) {
            foreach ($recipient->enabledChannels('schedule_changes') as $channel) {
                SendScheduleChangeNotice::dispatch(
                    $event->id,
                    $recipient->id,
                    $channel,
                    $change,
                    $previous['starts_at']->toIso8601String(),
                    $previous['ends_at']->toIso8601String(),
                    $previous['location'],
                )->afterCommit();
            }
        }
    }
}
