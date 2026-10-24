<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\Channels\DeliveryChannel;
use App\Services\Notifications\Channels\MailChannel;
use App\Services\Notifications\Channels\PushChannel;
use App\Services\Notifications\Channels\SmsChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sends a message on one channel and records the attempt in the delivery history.
 */
class NotificationSender
{
    public function __construct(
        private MailChannel $mail,
        private SmsChannel $sms,
        private PushChannel $push,
    ) {}

    /**
     * Get the channel implementation.
     */
    public function channel(NotificationChannel $channel): DeliveryChannel
    {
        return match ($channel) {
            NotificationChannel::Mail => $this->mail,
            NotificationChannel::Sms => $this->sms,
            NotificationChannel::Push => $this->push,
        };
    }

    /**
     * Attempt delivery and record the outcome.
     */
    public function send(
        User $user,
        NotificationChannel $channel,
        OutboundMessage $message,
        ?Model $deliverable = null,
        int $attemptNumber = 1,
        ?CarbonImmutable $scheduledFor = null,
    ): DeliveryResult {
        $result = $this->channel($channel)->send($user, $message);

        $this->record($user, $channel, $message->type, $result, $deliverable, $attemptNumber, $scheduledFor);

        return $result;
    }

    /**
     * Record a delivery attempt.
     */
    public function record(
        User $user,
        NotificationChannel $channel,
        string $type,
        DeliveryResult $result,
        ?Model $deliverable = null,
        int $attemptNumber = 1,
        ?CarbonImmutable $scheduledFor = null,
    ): NotificationDelivery {
        $delivery = new NotificationDelivery;
        $delivery->user_id = $user->id;
        $delivery->notification_type = $type;
        $delivery->channel = $channel;
        $delivery->status = $result->status;
        $delivery->attempt_number = $attemptNumber;
        $delivery->scheduled_for = $scheduledFor;
        $delivery->attempted_at = now();
        $delivery->provider_message_id = $result->providerMessageId !== null
            ? substr($result->providerMessageId, 0, 250)
            : null;
        $delivery->error_message = ErrorSanitizer::clean($result->error);

        if ($deliverable !== null) {
            $delivery->deliverable()->associate($deliverable);
        }

        $delivery->save();

        return $delivery;
    }
}
