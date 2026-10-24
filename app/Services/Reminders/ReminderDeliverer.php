<?php

namespace App\Services\Reminders;

use App\Enums\DeliveryStatus;
use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Services\Notifications\AlertGate;
use App\Services\Notifications\ErrorSanitizer;
use App\Services\Notifications\InAppNotifier;
use App\Services\Notifications\NotificationSender;
use App\Support\NotificationCategories;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Performs one delivery attempt for a queued reminder.
 *
 * State transitions are conditional updates (queued → processing → terminal), so two
 * workers holding the same reminder cannot both deliver it.
 */
class ReminderDeliverer
{
    public function __construct(
        private ReminderPlanner $planner,
        private ReminderMessageFactory $messages,
        private NotificationSender $sender,
        private InAppNotifier $inApp,
        private AlertGate $gate,
    ) {}

    /**
     * Deliver the reminder.
     *
     * @throws TransientDeliveryException when the attempt should be retried later.
     */
    public function deliver(int $reminderId, bool $isFinalAttempt): void
    {
        $claimed = Reminder::query()
            ->whereKey($reminderId)
            ->where('status', ReminderStatus::Queued->value)
            ->update([
                'status' => ReminderStatus::Processing->value,
                'reserved_at' => now(),
                'last_attempt_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $reminder = Reminder::query()->with(['user', 'remindable'])->findOrFail($reminderId);

        if (! $reminder->user->is_active) {
            $this->finish($reminder, ReminderStatus::Skipped, reason: 'Recipient account is deactivated.');

            return;
        }

        $expected = $this->planner->expectedScheduleFor($reminder);

        if ($expected === null || ! $expected->equalTo($reminder->scheduled_for)) {
            $this->finish($reminder, ReminderStatus::Skipped, reason: 'No longer relevant: the record changed, was completed, or was cancelled.');

            return;
        }

        $message = $this->messages->make($reminder);
        $category = NotificationCategories::forReminder($reminder->kind);

        // One bell item per reminder time, however many channels deliver it.
        $this->inApp->notify(
            $reminder->user,
            "reminder:{$reminder->remindable_type}:{$reminder->remindable_id}:{$reminder->kind->value}:{$reminder->minutes_before}:{$reminder->scheduled_for->timestamp}",
            $message->type,
            $message->title,
            $message->body,
            $message->url,
            $category,
        );

        if (! $reminder->user->canReceiveOn($reminder->channel, $category)) {
            $this->finish($reminder, ReminderStatus::Skipped, reason: 'The recipient has turned off this channel.');

            return;
        }

        // Reminders are never digested, so only quiet hours can hold one back here.
        if ($this->gate->admit($reminder->user, $category, $reminder->channel, $message->title, $message->body, $message->url) !== AlertGate::SEND) {
            $this->finish($reminder, ReminderStatus::Skipped, reason: 'Held back during the recipient’s quiet hours.');

            return;
        }

        try {
            $result = $this->sender->send(
                $reminder->user,
                $reminder->channel,
                $message,
                $reminder,
                $reminder->attempts,
                $reminder->scheduled_for,
            );
        } catch (Throwable $exception) {
            $this->requeueOrFail($reminder, 'Unexpected error: '.class_basename($exception), $isFinalAttempt);

            throw $exception;
        }

        match (true) {
            $result->status === DeliveryStatus::Sent => $this->finish($reminder, ReminderStatus::Sent, providerMessageId: $result->providerMessageId),
            $result->status === DeliveryStatus::Unavailable => $this->finish($reminder, ReminderStatus::Unavailable, error: $result->error),
            $result->retryable && ! $isFinalAttempt => $this->requeueOrFail($reminder, (string) $result->error, false),
            default => $this->finish($reminder, ReminderStatus::Failed, error: $result->error),
        };

        if ($reminder->status === ReminderStatus::Queued) {
            throw new TransientDeliveryException("Reminder {$reminder->id} will be retried.");
        }
    }

    /**
     * Mark a reminder failed after its final attempt, when it is still in flight.
     */
    public function markFailed(int $reminderId, string $error): void
    {
        Reminder::query()
            ->whereKey($reminderId)
            ->whereIn('status', [ReminderStatus::Queued->value, ReminderStatus::Processing->value])
            ->update([
                'status' => ReminderStatus::Failed->value,
                'last_error' => ErrorSanitizer::clean($error),
                'reserved_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Put the reminder back in the queued state for a retry, or fail it on the final attempt.
     */
    private function requeueOrFail(Reminder $reminder, string $error, bool $isFinalAttempt): void
    {
        if ($isFinalAttempt) {
            $this->finish($reminder, ReminderStatus::Failed, error: $error);

            return;
        }

        $reminder->forceFill([
            'status' => ReminderStatus::Queued,
            'last_error' => ErrorSanitizer::clean($error),
            'reserved_at' => now(),
        ])->save();
    }

    /**
     * Move the reminder to a terminal state.
     */
    private function finish(
        Reminder $reminder,
        ReminderStatus $status,
        ?string $providerMessageId = null,
        ?string $error = null,
        ?string $reason = null,
    ): void {
        $reminder->forceFill([
            'status' => $status,
            'sent_at' => $status === ReminderStatus::Sent ? now() : null,
            'provider_message_id' => $providerMessageId !== null ? substr($providerMessageId, 0, 250) : null,
            'last_error' => ErrorSanitizer::clean($error),
            'status_reason' => $reason,
            'reserved_at' => null,
        ])->save();
    }
}
