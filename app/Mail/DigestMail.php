<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A summary of the email alerts held back since the previous digest.
 */
class DigestMail extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     *
     * @param  array<string, array<int, array{title: string, body: string, url: string|null, at: string}>>  $sections  Category label => items.
     */
    public function __construct(
        public string $subjectLine,
        public string $intro,
        public array $sections,
        public string $actionUrl,
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
            markdown: 'mail.digest',
        );
    }
}
