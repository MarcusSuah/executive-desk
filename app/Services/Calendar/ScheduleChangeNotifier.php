<?php

namespace App\Services\Calendar;

use App\Jobs\SendScheduleChangeNotice;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Notifications\InAppNotifier;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;

/**
 * Tells the owner and internal attendees (other than the person making the change)
 * that an appointment moved, changed location, or was cancelled.
 */
class ScheduleChangeNotifier
{
    public function __construct(private InAppNotifier $inApp) {}

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
            if (! $recipient->preferences()->schedule_change_notifications) {
                continue;
            }

            $this->inApp->notify(
                $recipient,
                "schedule:{$event->id}:{$event->version}",
                'schedule_change',
                ucfirst($change).': '.$event->title,
                $change === 'cancelled'
                    ? 'This appointment has been cancelled.'
                    : 'New time: '.DisplayTime::range($event->starts_at, $event->ends_at, $recipient->timezone),
                route('events.show', $event),
            );

            foreach ($recipient->enabledChannels() as $channel) {
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
