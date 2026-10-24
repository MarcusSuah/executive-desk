<?php

namespace App\Services\Notifications;

use App\Enums\DeliveryStatus;

/**
 * The outcome of one delivery attempt on one channel.
 *
 * "Sent" means the provider accepted the message. It does not prove the
 * message reached the recipient's inbox, phone, or device.
 */
final readonly class DeliveryResult
{
    public function __construct(
        public DeliveryStatus $status,
        public ?string $providerMessageId = null,
        public ?string $error = null,
        public bool $retryable = false,
    ) {}

    /**
     * The provider accepted the message.
     */
    public static function sent(?string $providerMessageId = null): self
    {
        return new self(DeliveryStatus::Sent, $providerMessageId);
    }

    /**
     * A failure that may succeed if retried later (network errors, rate limits, 5xx responses).
     */
    public static function transientFailure(string $error): self
    {
        return new self(DeliveryStatus::Failed, error: $error, retryable: true);
    }

    /**
     * A failure that will not succeed on retry (invalid address, rejected payload).
     */
    public static function permanentFailure(string $error): self
    {
        return new self(DeliveryStatus::Failed, error: $error);
    }

    /**
     * The channel cannot be used (not configured, no verified phone, no registered device).
     */
    public static function unavailable(string $reason): self
    {
        return new self(DeliveryStatus::Unavailable, error: $reason);
    }

    /**
     * Determine whether the provider accepted the message.
     */
    public function wasSent(): bool
    {
        return $this->status === DeliveryStatus::Sent;
    }
}
