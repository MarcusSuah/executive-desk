<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Mail\DigestMail;
use App\Models\DigestItem;
use App\Models\User;
use App\Services\Notifications\NotificationSender;
use App\Services\Notifications\OutboundMessage;
use App\Services\Reminders\TransientDeliveryException;
use App\Support\DisplayTime;
use App\Support\NotificationCategories;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Emails a person the alerts held back for their digest.
 *
 * Items are marked as digested only after the email is accepted, so a failed send
 * leaves them for the next attempt or the next digest.
 */
class SendDigest implements ShouldQueue
{
    use Queueable;

    /**
     * The most items listed in one digest; any others wait for the next one.
     */
    public const MAX_ITEMS = 100;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $userId) {}

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
    public function handle(NotificationSender $sender): void
    {
        $user = User::find($this->userId);

        if ($user === null || ! $user->is_active) {
            return;
        }

        $items = $user->digestItems()->whereNull('digested_at')->oldest('id')->limit(self::MAX_ITEMS)->get();

        if ($items->isEmpty()) {
            return;
        }

        $sections = [];

        foreach ($items as $item) {
            $label = NotificationCategories::DEFINITIONS[$item->category]['label'] ?? 'Other alerts';
            $sections[$label][] = [
                'title' => $item->title,
                'body' => $item->body,
                'url' => $item->url,
                'at' => DisplayTime::forUser(CarbonImmutable::instance($item->created_at ?? now()), $user),
            ];
        }

        $count = $items->count();
        $title = $count === 1 ? 'Your digest: 1 update' : "Your digest: {$count} updates";
        $period = $user->preferences()->digest_frequency === 'weekly' ? 'this week' : 'since your last digest';

        $result = $sender->send($user, NotificationChannel::Mail, new OutboundMessage(
            type: 'digest',
            title: $title,
            body: "Updates {$period}.",
            mailable: new DigestMail(
                subjectLine: $title,
                intro: "Here is what happened {$period}.",
                sections: $sections,
                actionUrl: route('dashboard'),
            ),
            smsText: $title,
            url: route('dashboard'),
        ), null, $this->attempts());

        if ($result->status === DeliveryStatus::Sent) {
            DigestItem::query()->whereKey($items->modelKeys())->update(['digested_at' => now()]);

            return;
        }

        if ($result->retryable && $this->attempts() < $this->tries) {
            throw new TransientDeliveryException("Digest for user {$user->id} will be retried.");
        }
    }
}
