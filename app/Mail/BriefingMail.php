<?php

namespace App\Mail;

use App\Enums\BriefingType;
use App\Models\ExecutiveBriefing;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The daily or weekly executive briefing.
 */
class BriefingMail extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(public ExecutiveBriefing $briefing) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->label().' — '.$this->period(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $timezone = $this->briefing->timezone;

        return new Content(
            markdown: 'mail.briefing',
            with: [
                'label' => $this->label(),
                'period' => $this->period(),
                'headline' => $this->briefing->content['headline'] ?? '',
                'sections' => $this->briefing->content['sections'] ?? [],
                'timezone' => $timezone,
                'format' => fn (?string $iso, string $pattern = 'D, M j g:i A'): string => $iso === null
                    ? ''
                    : CarbonImmutable::parse($iso)->setTimezone($timezone)->format($pattern),
                'url' => route('briefings.show', $this->briefing),
            ],
        );
    }

    /**
     * Get the briefing label.
     */
    private function label(): string
    {
        return $this->briefing->type === BriefingType::Daily ? 'Daily briefing' : 'Weekly briefing';
    }

    /**
     * Describe the period covered.
     */
    private function period(): string
    {
        $start = $this->briefing->period_start;
        $end = $this->briefing->period_end;

        return $start->equalTo($end)
            ? $start->format('l, F j, Y')
            : $start->format('M j').' – '.$end->format('M j, Y');
    }
}
