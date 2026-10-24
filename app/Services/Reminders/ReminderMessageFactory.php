<?php

namespace App\Services\Reminders;

use App\Enums\ReminderKind;
use App\Mail\NoticeMail;
use App\Models\CalendarEvent;
use App\Models\Presentation;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Services\Notifications\OutboundMessage;
use App\Support\DisplayTime;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Renders a reminder for every channel in the recipient's time zone.
 */
class ReminderMessageFactory
{
    /**
     * Build the outbound message for a reminder.
     */
    public function make(Reminder $reminder): OutboundMessage
    {
        $remindable = $reminder->remindable;
        $user = $reminder->user;
        $lead = DisplayTime::minutes($reminder->minutes_before);

        return match (true) {
            $remindable instanceof CalendarEvent => $this->forEvent($remindable, $user, $lead, $reminder),
            $remindable instanceof Task => $this->forTask($remindable, $user, $lead, $reminder),
            $remindable instanceof Presentation => $this->forPresentation($remindable, $user, $lead, $reminder),
            default => throw new InvalidArgumentException('Reminder has no deliverable record.'),
        };
    }

    /**
     * Build a meeting or appointment reminder.
     */
    private function forEvent(CalendarEvent $event, User $user, string $lead, Reminder $reminder): OutboundMessage
    {
        $when = DisplayTime::range($event->starts_at, $event->ends_at, $user->timezone);
        $url = route('events.show', $event);

        $details = array_filter([
            'When' => $when,
            'Type' => $event->type->label(),
            'Priority' => $event->priority->label(),
            'Location' => $event->location,
            'Meeting link' => $event->meeting_url,
            'Event time zone' => $event->timezone,
        ]);

        return new OutboundMessage(
            type: 'event_reminder',
            title: "In {$lead}: {$event->title}",
            body: trim($when.($event->location ? " · {$event->location}" : '')),
            mailable: new NoticeMail(
                subjectLine: "Reminder: {$event->title} starts in {$lead}",
                heading: $event->title,
                intro: "This is a reminder that the following {$this->lower($event->type->label())} starts in {$lead}.",
                details: $details,
                bodyTitle: $event->agenda ? 'Agenda' : null,
                body: $event->agenda,
                actionUrl: $url,
                actionLabel: 'View appointment',
            ),
            smsText: Str::limit("Reminder: {$event->title} in {$lead} ({$when})".($event->location ? " at {$event->location}" : ''), 300),
            url: $url,
            tag: "event-{$event->id}",
        );
    }

    /**
     * Build a task deadline reminder.
     */
    private function forTask(Task $task, User $user, string $lead, Reminder $reminder): OutboundMessage
    {
        $due = DisplayTime::forUser($task->due_at, $user);
        $url = route('tasks.show', $task);

        return new OutboundMessage(
            type: 'task_reminder',
            title: "Task due in {$lead}: {$task->title}",
            body: "Due {$due} · {$task->priority->label()} priority",
            mailable: new NoticeMail(
                subjectLine: "Task due in {$lead}: {$task->title}",
                heading: $task->title,
                intro: "This task is due in {$lead}.",
                details: array_filter([
                    'Due' => $due,
                    'Priority' => $task->priority->label(),
                    'Status' => $task->status->label(),
                    'Project' => $task->project,
                ]),
                bodyTitle: $task->description ? 'Details' : null,
                body: $task->description,
                actionUrl: $url,
                actionLabel: 'View task',
            ),
            smsText: Str::limit("Task due in {$lead}: {$task->title} ({$task->priority->label()}) - due {$due}", 300),
            url: $url,
            tag: "task-{$task->id}",
        );
    }

    /**
     * Build a presentation preparation or review deadline reminder.
     */
    private function forPresentation(Presentation $presentation, User $user, string $lead, Reminder $reminder): OutboundMessage
    {
        $isReview = $reminder->kind === ReminderKind::PresentationReview;
        $deadlineLabel = $isReview ? 'Review deadline' : 'Preparation deadline';
        $deadline = DisplayTime::forUser($isReview ? $presentation->review_deadline_at : $presentation->preparation_deadline_at, $user);
        $url = route('presentations.show', $presentation);

        return new OutboundMessage(
            type: 'presentation_reminder',
            title: "{$deadlineLabel} in {$lead}: {$presentation->title}",
            body: "{$deadlineLabel}: {$deadline}",
            mailable: new NoticeMail(
                subjectLine: "{$deadlineLabel} in {$lead}: {$presentation->title}",
                heading: $presentation->title,
                intro: "The {$this->lower($deadlineLabel)} for this presentation is in {$lead}.",
                details: array_filter([
                    $deadlineLabel => $deadline,
                    'Presentation' => $presentation->presentation_at ? DisplayTime::forUser($presentation->presentation_at, $user) : null,
                    'Status' => $presentation->status->label(),
                    'Venue' => $presentation->venue,
                ]),
                actionUrl: $url,
                actionLabel: 'View presentation',
            ),
            smsText: Str::limit("{$deadlineLabel} in {$lead}: {$presentation->title} - {$deadline}", 300),
            url: $url,
            tag: "presentation-{$presentation->id}",
        );
    }

    /**
     * Lower-case a label for use mid-sentence.
     */
    private function lower(string $label): string
    {
        return Str::lower($label);
    }
}
