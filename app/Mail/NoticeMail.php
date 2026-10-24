<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A reminder or schedule-change notice with a details table and a link to the record.
 */
class NoticeMail extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     *
     * @param  array<string, string>  $details  Label => value rows shown in the details table.
     */
    public function __construct(
        public string $subjectLine,
        public string $heading,
        public string $intro,
        public array $details = [],
        public ?string $bodyTitle = null,
        public ?string $body = null,
        public ?string $actionUrl = null,
        public string $actionLabel = 'Open in Executive Desk',
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notice',
        );
    }
}
