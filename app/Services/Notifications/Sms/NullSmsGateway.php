<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\DeliveryResult;

/**
 * Used when no SMS provider has been configured. Every send is reported as unavailable.
 */
class NullSmsGateway implements SmsGateway
{
    /**
     * Determine whether the gateway is configured and able to send.
     */
    public function isConfigured(): bool
    {
        return false;
    }

    /**
     * Get the gateway's display name.
     */
    public function name(): string
    {
        return 'Not configured';
    }

    /**
     * Report the message as undeliverable.
     */
    public function send(string $phoneNumber, string $message): DeliveryResult
    {
        return DeliveryResult::unavailable('No SMS provider is configured.');
    }
}
