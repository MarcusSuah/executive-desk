<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\DeliveryResult;

/**
 * Provider-independent SMS gateway.
 *
 * A provider adapter must return DeliveryResult::sent() only when the provider has
 * accepted the message, and include the provider's message identifier so delivery
 * receipts can be reconciled later.
 */
interface SmsGateway
{
    /**
     * Determine whether the gateway is configured and able to send.
     */
    public function isConfigured(): bool;

    /**
     * Get the gateway's display name.
     */
    public function name(): string;

    /**
     * Send a text message to an E.164 phone number.
     */
    public function send(string $phoneNumber, string $message): DeliveryResult;
}
