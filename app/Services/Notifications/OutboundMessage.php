<?php

namespace App\Services\Notifications;

use Illuminate\Mail\Mailable;

/**
 * A channel-independent notification with a rendering for each channel.
 */
final readonly class OutboundMessage
{
    /**
     * @param  string  $type  Notification type recorded in the delivery history (e.g. "event_reminder").
     * @param  string  $title  Short title used by push notifications.
     * @param  string  $body  Short body used by push notifications.
     * @param  string  $smsText  Plain text for SMS (kept under 320 characters).
     * @param  string|null  $url  Absolute URL that opens the related record.
     * @param  string|null  $tag  Push tag that lets a newer notification replace an older one.
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public Mailable $mailable,
        public string $smsText,
        public ?string $url = null,
        public ?string $tag = null,
    ) {}
}
