<?php

namespace App\Services\Notifications;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\ExecutiveBriefing;
use App\Models\Presentation;
use App\Models\Task;
use App\Models\User;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Turns audited activity into in-app notifications for everyone involved in the
 * affected record, except the person who did it.
 *
 * - Appointments: the executive, internal attendees, and whoever scheduled it.
 * - Tasks: the assignee and the creator.
 * - Presentations: the executive, the presenter, and the creator.
 * - Briefings: the recipient.
 * - Accounts and application settings: administrators, plus the affected user.
 */
class ActivityNotifier
{
    /**
     * Actions that are recorded but not announced (sign-ins, page views, downloads,
     * exports, personal settings, and system housekeeping).
     *
     * @var array<int, string>
     */
    private const SILENT_PREFIXES = [
        'auth.', 'page.', 'request.', 'export.', 'phone.', 'push.', 'notification_preferences.',
        'profile.', 'security.', 'inbox.',
    ];

    /**
     * @var array<int, string>
     */
    private const SILENT_ACTIONS = [
        'attachment.downloaded', 'task.occurrence_generated', 'briefing.sent', 'reminder.retried',
    ];

    /**
     * Verbs used in notification titles.
     *
     * @var array<string, string>
     */
    private const VERBS = [
        'event.created' => 'scheduled',
        'event.updated' => 'updated',
        'event.rescheduled' => 'rescheduled',
        'event.cancelled' => 'cancelled',
        'event.completed' => 'marked as completed',
        'event.deleted' => 'deleted',
        'task.created' => 'created the task',
        'task.updated' => 'updated the task',
        'task.reassigned' => 'reassigned the task',
        'task.completed' => 'completed the task',
        'task.reopened' => 'reopened the task',
        'task.cancelled' => 'cancelled the task',
        'task.status_changed' => 'changed the status of',
        'task.deleted' => 'deleted the task',
        'presentation.created' => 'created the presentation',
        'presentation.updated' => 'updated the presentation',
        'presentation.approved' => 'approved the presentation',
        'presentation.deleted' => 'deleted the presentation',
        'checklist.item_added' => 'added a checklist item to',
        'checklist.item_completed' => 'ticked a checklist item on',
        'checklist.item_reopened' => 'reopened a checklist item on',
        'checklist.item_removed' => 'removed a checklist item from',
        'attachment.uploaded' => 'uploaded a file to',
        'attachment.deleted' => 'removed a file from',
        'reminder.disabled' => 'turned off a reminder for',
        'reminder.enabled' => 'turned on a reminder for',
        'briefing.generated' => 'prepared',
        'user.created' => 'created the account for',
        'user.updated' => 'updated the account of',
        'user.roles_changed' => 'changed the roles of',
        'user.deactivated' => 'deactivated the account of',
        'user.reactivated' => 'reactivated the account of',
        'user.password_reset_sent' => 'sent a password reset link to',
        'user.deleted' => 'deleted the account of',
    ];

    public function __construct(private InAppNotifier $inApp) {}

    /**
     * Notify everyone involved in the activity.
     */
    public function notify(AuditLog $log, ?Model $auditable, ?User $actor): void
    {
        if ($this->isSilent($log->action)) {
            return;
        }

        // A new task's assignee is told by the "task.assigned" entry that follows.
        $toldSeparately = $log->action === 'task.created' && $auditable instanceof Task ? $auditable->assigned_to : null;

        $recipients = User::query()
            ->active()
            ->whereIn('id', $this->recipientIds($log, $auditable))
            ->whereKeyNot(array_values(array_filter([$actor?->id, $toldSeparately])))
            ->get();

        foreach ($recipients as $recipient) {
            [$title, $body, $url] = $this->describe($log, $auditable, $actor, $recipient);

            $this->inApp->notify(
                $recipient,
                "activity:{$log->id}",
                'activity_'.($log->auditable_type ?? Str::before($log->action, '.')),
                $title,
                $body,
                $url,
            );
        }
    }

    /**
     * Determine whether an action is recorded without a notification.
     */
    private function isSilent(string $action): bool
    {
        return in_array($action, self::SILENT_ACTIONS, true)
            || Str::startsWith($action, self::SILENT_PREFIXES);
    }

    /**
     * Get the IDs of the people involved in the record.
     *
     * @return array<int, int>
     */
    private function recipientIds(AuditLog $log, ?Model $auditable): array
    {
        $ids = match (true) {
            $auditable instanceof CalendarEvent => [
                $auditable->owner_id,
                $auditable->created_by,
                ...$auditable->attendees()->pluck('user_id')->all(),
            ],
            $auditable instanceof Task => [$auditable->assigned_to, $auditable->created_by],
            $auditable instanceof Presentation => [$auditable->owner_id, $auditable->presenter_id, $auditable->created_by],
            $auditable instanceof ExecutiveBriefing => [$auditable->user_id],
            $auditable instanceof User => [$auditable->id, ...$this->administratorIds()],
            Str::startsWith($log->action, 'settings.') => $this->administratorIds(),
            default => [],
        };

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Get the IDs of active administrators.
     *
     * @return array<int, int>
     */
    private function administratorIds(): array
    {
        return User::query()->active()->role(Role::Administrator->value)->pluck('id')->all();
    }

    /**
     * Build the title, body, and link for one recipient.
     *
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function describe(AuditLog $log, ?Model $auditable, ?User $actor, User $recipient): array
    {
        $who = $actor->name ?? 'The system';
        $subject = $this->subject($auditable, $recipient);
        $new = $log->new_values ?? [];

        if ($log->action === 'task.assigned' && $auditable instanceof Task) {
            return [
                'New task: '.$auditable->title,
                "Assigned by {$who}".($auditable->due_at ? ' · due '.DisplayTime::forUser($auditable->due_at, $recipient) : ''),
                route('tasks.show', $auditable),
            ];
        }

        $verb = self::VERBS[$log->action] ?? Str::lower(Str::headline(Str::after($log->action, '.')));
        $title = Str::startsWith($log->action, 'settings.')
            ? "{$who} changed the application settings"
            : trim("{$who} {$verb} {$subject}");

        return [Str::limit($title, 180), Str::limit($this->body($log, $auditable, $recipient, $new), 240), $this->url($log, $auditable, $recipient)];
    }

    /**
     * Name the affected record, e.g. “Board Meeting”.
     */
    private function subject(?Model $auditable, User $recipient): string
    {
        return match (true) {
            $auditable instanceof CalendarEvent, $auditable instanceof Task, $auditable instanceof Presentation => '“'.$auditable->getAttribute('title').'”',
            $auditable instanceof ExecutiveBriefing => $auditable->user_id === $recipient->id
                ? "your {$auditable->type->value} briefing"
                : "the {$auditable->type->value} briefing for {$auditable->user->name}",
            $auditable instanceof User => $auditable->id === $recipient->id ? 'your account' : $auditable->name,
            default => '',
        };
    }

    /**
     * Summarize what changed.
     *
     * @param  array<string, mixed>  $new
     */
    private function body(AuditLog $log, ?Model $auditable, User $recipient, array $new): string
    {
        if (in_array($log->action, ['event.rescheduled', 'event.created'], true) && $auditable instanceof CalendarEvent) {
            return 'When: '.DisplayTime::range($auditable->starts_at, $auditable->ends_at, $recipient->timezone);
        }

        if ($log->action === 'event.cancelled') {
            return isset($new['cancellation_reason']) && is_string($new['cancellation_reason'])
                ? 'Reason: '.$new['cancellation_reason']
                : 'This appointment has been cancelled.';
        }

        foreach (['title' => 'Item', 'file' => 'File'] as $key => $label) {
            if (isset($new[$key]) && is_string($new[$key]) && Str::startsWith($log->action, ['checklist.', 'attachment.'])) {
                return "{$label}: {$new[$key]}";
            }
        }

        if (isset($new['status']) && is_string($new['status'])) {
            return 'Status: '.Str::headline($new['status']);
        }

        if (isset($new['roles']) && is_array($new['roles'])) {
            return 'Roles: '.implode(', ', array_map(fn ($role) => Str::headline((string) $role), $new['roles']));
        }

        if ($auditable instanceof Task && $auditable->due_at !== null && Str::startsWith($log->action, 'task.')) {
            return 'Due '.DisplayTime::forUser($auditable->due_at, $recipient);
        }

        $fields = array_diff(array_keys($new), ['attachment_id', 'reminder_id', 'subscription_id', 'source']);

        if ($fields !== []) {
            return 'Changed: '.implode(', ', array_map(fn (string $field) => Str::headline($field), array_slice($fields, 0, 5)));
        }

        return 'At '.DisplayTime::forUser(CarbonImmutable::instance($log->created_at), $recipient);
    }

    /**
     * Link to the affected record when it still exists and the recipient can open it.
     */
    private function url(AuditLog $log, ?Model $auditable, User $recipient): ?string
    {
        if (Str::endsWith($log->action, '.deleted') && ! $auditable instanceof User) {
            return null;
        }

        return match (true) {
            $auditable instanceof CalendarEvent => route('events.show', $auditable),
            $auditable instanceof Task => route('tasks.show', $auditable),
            $auditable instanceof Presentation => route('presentations.show', $auditable),
            $auditable instanceof ExecutiveBriefing => route('briefings.show', $auditable),
            $auditable instanceof User => $auditable->id === $recipient->id ? route('profile.edit') : route('admin.users.edit', $auditable),
            Str::startsWith($log->action, 'settings.') => route('admin.settings.edit'),
            default => null,
        };
    }
}
