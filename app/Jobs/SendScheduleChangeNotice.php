<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Mail\NoticeMail;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Notifications\AlertGate;
use App\Services\Notifications\NotificationSender;
use App\Services\Notifications\OutboundMessage;
use App\Services\Reminders\TransientDeliveryException;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SendScheduleChangeNotice implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param  'rescheduled'|'updated'|'cancelled'  $change
     */
    public function __construct(
        public int $eventId,
        public int $userId,
        public NotificationChannel $channel,
        public string $change,
        public string $previousStartsAt,
        public string $previousEndsAt,
        public ?string $previousLocation,
    ) {}

    /**
     * The exponential backoff, in seconds, between attempts.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationSender $sender, AlertGate $gate): void
    {
        $event = CalendarEvent::withTrashed()->find($this->eventId);
        $user = User::find($this->userId);

        if ($event === null || $user === null || ! $user->is_active || ! $user->canReceiveOn($this->channel, 'schedule_changes')) {
            return;
        }

        $message = $this->message($event, $user);

        // Only the first attempt decides; a retry is already past the gate.
        if ($this->attempts() === 1 && $gate->admit($user, 'schedule_changes', $this->channel, $message->title, $message->body, $message->url) !== AlertGate::SEND) {
            return;
        }

        $result = $sender->send($user, $this->channel, $message, $event, $this->attempts());

        if ($result->retryable && $this->attempts() < $this->tries) {
            throw new TransientDeliveryException("Schedule change notice for event {$event->id} will be retried.");
        }
    }

    /**
     * Build the notice.
     */
    private function message(CalendarEvent $event, User $user): OutboundMessage
    {
        $previous = DisplayTime::range(
            CarbonImmutable::parse($this->previousStartsAt),
            CarbonImmutable::parse($this->previousEndsAt),
            $user->timezone,
        );
        $current = DisplayTime::range($event->starts_at, $event->ends_at, $user->timezone);
        $url = route('events.show', $event);

        [$headline, $intro, $details] = match ($this->change) {
            'cancelled' => [
                "Cancelled: {$event->title}",
                'This appointment has been cancelled.',
                array_filter(['Was scheduled for' => $previous, 'Reason' => $event->cancellation_reason]),
            ],
            'rescheduled' => [
                "Rescheduled: {$event->title}",
                'This appointment has a new time.',
                array_filter(['New time' => $current, 'Previous time' => $previous, 'Location' => $event->location, 'Meeting link' => $event->meeting_url]),
            ],
            default => [
                "Updated: {$event->title}",
                'The location or meeting link for this appointment has changed.',
                array_filter(['When' => $current, 'Location' => $event->location, 'Previous location' => $this->previousLocation, 'Meeting link' => $event->meeting_url]),
            ],
        };

        return new OutboundMessage(
            type: 'schedule_change',
            title: $headline,
            body: $this->change === 'cancelled' ? "Was: {$previous}" : $current,
            mailable: new NoticeMail(
                subjectLine: $headline,
                heading: $headline,
                intro: $intro,
                details: $details,
                actionUrl: $url,
                actionLabel: 'View appointment',
            ),
            smsText: Str::limit($headline.' - '.($this->change === 'cancelled' ? "was {$previous}" : $current), 300),
            url: $url,
            tag: "event-{$event->id}",
        );
    }
}
