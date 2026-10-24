<?php

namespace App\Services\Notifications\Push;

final readonly class PushOutcome
{
    public function __construct(
        public bool $success,
        public bool $expired = false,
        public bool $retryable = false,
        public ?string $reason = null,
        public ?string $messageId = null,
    ) {}

    /**
     * The push service accepted the message.
     */
    public static function accepted(?string $messageId = null): self
    {
        return new self(true, messageId: $messageId);
    }

    /**
     * The subscription no longer exists (HTTP 404 or 410) and should be removed.
     */
    public static function expired(): self
    {
        return new self(false, expired: true, reason: 'Subscription expired.');
    }

    /**
     * The push service rejected or failed the request.
     */
    public static function failed(string $reason, bool $retryable): self
    {
        return new self(false, retryable: $retryable, reason: $reason);
    }
}
