<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Mail\NoticeMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Notifications\AlertGate;
use App\Services\Notifications\NotificationSender;
use App\Services\Notifications\OutboundMessage;
use App\Services\Reminders\TransientDeliveryException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Sends an activity alert (a task assignment, an account change, and so on) by email,
 * SMS, or push to someone whose category settings ask for it.
 */
class SendActivityNotice implements ShouldQueue
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
     * @param  string|null  $url  Absolute URL of the affected record.
     */
    public function __construct(
        public int $auditLogId,
        public int $userId,
        public NotificationChannel $channel,
        public string $category,
        public string $title,
        public string $body,
        public ?string $url,
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
        $user = User::find($this->userId);

        if ($user === null || ! $user->is_active || ! $user->canReceiveOn($this->channel, $this->category)) {
            return;
        }

        // Only the first attempt decides; a retry is already past the gate.
        if ($this->attempts() === 1 && $gate->admit($user, $this->category, $this->channel, $this->title, $this->body, $this->url) !== AlertGate::SEND) {
            return;
        }

        $result = $sender->send($user, $this->channel, new OutboundMessage(
            type: 'activity',
            title: $this->title,
            body: $this->body,
            mailable: new NoticeMail(
                subjectLine: $this->title,
                heading: $this->title,
                intro: $this->body,
                actionUrl: $this->url,
                actionLabel: 'Open in Executive Desk',
            ),
            smsText: Str::limit("{$this->title} - {$this->body}", 300),
            url: $this->url,
            tag: "activity-{$this->auditLogId}",
        ), AuditLog::find($this->auditLogId), $this->attempts());

        if ($result->retryable && $this->attempts() < $this->tries) {
            throw new TransientDeliveryException("Activity notice {$this->auditLogId} will be retried.");
        }
    }
}
