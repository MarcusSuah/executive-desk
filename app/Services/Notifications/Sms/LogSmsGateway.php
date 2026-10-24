<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\DeliveryResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development gateway that writes messages to the application log instead of sending them.
 */
class LogSmsGateway implements SmsGateway
{
    /**
     * Determine whether the gateway is configured and able to send.
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * Get the gateway's display name.
     */
    public function name(): string
    {
        return 'Log (development only)';
    }

    /**
     * Write the message to the log.
     */
    public function send(string $phoneNumber, string $message): DeliveryResult
    {
        $messageId = 'log-'.Str::uuid()->toString();

        Log::info('SMS (log driver)', ['to' => $phoneNumber, 'message' => $message, 'id' => $messageId]);

        return DeliveryResult::sent($messageId);
    }
}
