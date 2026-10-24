<?php

namespace App\Jobs;

use App\Enums\BriefingStatus;
use App\Enums\BriefingType;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Mail\BriefingMail;
use App\Models\ExecutiveBriefing;
use App\Services\Notifications\NotificationSender;
use App\Services\Notifications\OutboundMessage;
use App\Services\Reminders\TransientDeliveryException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SendBriefing implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 4;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $briefingId) {}

    /**
     * The exponential backoff, in seconds, between attempts.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * Deliver the briefing on each chosen channel that has not already succeeded.
     */
    public function handle(NotificationSender $sender): void
    {
        $briefing = ExecutiveBriefing::query()->with('user')->find($this->briefingId);

        if ($briefing === null || ! $briefing->user->is_active) {
            return;
        }

        $user = $briefing->user;
        $preferences = $user->preferences();
        $channelValues = $briefing->type === BriefingType::Daily
            ? $preferences->daily_briefing_channels
            : $preferences->weekly_briefing_channels;

        $alreadySent = $briefing->deliveries()
            ->where('status', DeliveryStatus::Sent->value)
            ->pluck('channel')
            ->map(fn (NotificationChannel $channel) => $channel->value)
            ->all();

        $results = [];
        $retry = false;

        foreach ($channelValues as $value) {
            $channel = NotificationChannel::tryFrom($value);

            if ($channel === null || in_array($value, $alreadySent, true)) {
                if ($channel !== null) {
                    $results[] = DeliveryStatus::Sent;
                }

                continue;
            }

            if (! $user->canReceiveOn($channel)) {
                $results[] = DeliveryStatus::Skipped;

                continue;
            }

            $result = $sender->send($user, $channel, $this->message($briefing), $briefing, $this->attempts());
            $results[] = $result->status;
            $retry = $retry || $result->retryable;
        }

        if ($retry && $this->attempts() < $this->tries) {
            throw new TransientDeliveryException("Briefing {$briefing->id} will be retried.");
        }

        $sent = count(array_filter($results, fn (DeliveryStatus $status) => $status === DeliveryStatus::Sent));

        $briefing->forceFill([
            'status' => match (true) {
                $sent > 0 && $sent === count($results) => BriefingStatus::Delivered,
                $sent > 0 => BriefingStatus::PartiallyDelivered,
                default => BriefingStatus::Failed,
            },
            'delivered_at' => $sent > 0 ? now() : null,
        ])->save();
    }

    /**
     * Build the briefing message for every channel.
     */
    private function message(ExecutiveBriefing $briefing): OutboundMessage
    {
        $label = $briefing->type === BriefingType::Daily ? 'Daily briefing' : 'Weekly briefing';
        $headline = (string) ($briefing->content['headline'] ?? $label);
        $url = route('briefings.show', $briefing);

        return new OutboundMessage(
            type: $briefing->type->value.'_briefing',
            title: $label,
            body: $headline,
            mailable: new BriefingMail($briefing),
            smsText: Str::limit("{$label}: {$headline}", 300),
            url: $url,
            tag: 'briefing-'.$briefing->type->value,
        );
    }
}
