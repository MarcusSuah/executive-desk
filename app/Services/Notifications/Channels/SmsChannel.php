<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\OutboundMessage;
use App\Services\Notifications\Sms\SmsGateway;

class SmsChannel implements DeliveryChannel
{
    public function __construct(private SmsGateway $gateway) {}

    /**
     * Determine whether the channel is configured on this installation.
     */
    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }

    /**
     * Send the message's SMS text to the user's verified phone number.
     */
    public function send(User $user, OutboundMessage $message): DeliveryResult
    {
        if (! $this->gateway->isConfigured()) {
            return DeliveryResult::unavailable('No SMS provider is configured.');
        }

        if (! $user->hasVerifiedPhone()) {
            return DeliveryResult::unavailable('The recipient has no verified phone number.');
        }

        return $this->gateway->send((string) $user->phone, $message->smsText);
    }
}
