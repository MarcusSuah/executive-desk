<?php

namespace App\Support;

use App\Enums\ReminderKind;
use Illuminate\Support\Str;

/**
 * The kinds of alert a person can tune individually: which channels each one uses
 * and how urgent it is.
 *
 * Priority matters in two places: only "high" alerts may break through quiet hours
 * (when the person allows it), and only non-high alerts are gathered into the email
 * digest. Reminders and briefings are time-bound, so they are never put in a digest.
 *
 * Reminders and schedule changes default to every channel, so the master channel
 * switches alone decide how they arrive until someone tunes a category.
 */
class NotificationCategories
{
    public const CHANNELS = ['in_app', 'mail', 'sms', 'push'];

    public const PRIORITIES = ['high', 'medium', 'low'];

    /**
     * The categories with their defaults.
     *
     * @var array<string, array{label: string, description: string, channels: array<int, string>, priority: string, digestible: bool, schedule: string|null}>
     */
    public const DEFINITIONS = [
        'meeting_reminders' => [
            'label' => 'Meeting reminders',
            'description' => 'Before an appointment or meeting starts.',
            'channels' => self::CHANNELS,
            'priority' => 'high',
            'digestible' => false,
            'schedule' => null,
        ],
        'task_reminders' => [
            'label' => 'Task deadlines',
            'description' => 'Before a task assigned to you is due.',
            'channels' => self::CHANNELS,
            'priority' => 'high',
            'digestible' => false,
            'schedule' => null,
        ],
        'presentation_reminders' => [
            'label' => 'Presentation deadlines',
            'description' => 'Before a presentation’s preparation or review deadline.',
            'channels' => self::CHANNELS,
            'priority' => 'medium',
            'digestible' => false,
            'schedule' => null,
        ],
        'schedule_changes' => [
            'label' => 'Schedule changes',
            'description' => 'An appointment you are part of is rescheduled, moved, or cancelled.',
            'channels' => self::CHANNELS,
            'priority' => 'high',
            'digestible' => true,
            'schedule' => null,
        ],
        'task_assignments' => [
            'label' => 'Task assignments',
            'description' => 'A task is assigned or reassigned to you.',
            'channels' => ['in_app', 'mail'],
            'priority' => 'medium',
            'digestible' => true,
            'schedule' => null,
        ],
        'record_activity' => [
            'label' => 'Activity on my records',
            'description' => 'Edits, checklist items, files, and approvals on things you are involved in.',
            'channels' => ['in_app'],
            'priority' => 'low',
            'digestible' => true,
            'schedule' => null,
        ],
        'account_security' => [
            'label' => 'Account & administration',
            'description' => 'Changes to your account, roles, permissions, or (for administrators) the system.',
            'channels' => ['in_app', 'mail'],
            'priority' => 'medium',
            'digestible' => true,
            'schedule' => null,
        ],
        'daily_briefing' => [
            'label' => 'Daily briefing',
            'description' => 'Today’s appointments, deadlines, and changes.',
            'channels' => ['in_app', 'mail'],
            'priority' => 'medium',
            'digestible' => false,
            'schedule' => 'daily',
        ],
        'weekly_briefing' => [
            'label' => 'Weekly briefing',
            'description' => 'The week ahead, busy periods, and last week’s completed work.',
            'channels' => ['in_app', 'mail'],
            'priority' => 'medium',
            'digestible' => false,
            'schedule' => 'weekly',
        ],
    ];

    /**
     * Get every category key.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * Get the default settings for every category.
     *
     * @return array<string, array<string, bool|string>>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            foreach (self::CHANNELS as $channel) {
                $defaults[$key][$channel] = in_array($channel, $definition['channels'], true);
            }

            $defaults[$key]['priority'] = $definition['priority'];
        }

        return $defaults;
    }

    /**
     * Get the category of a reminder.
     */
    public static function forReminder(ReminderKind $kind): string
    {
        return match ($kind) {
            ReminderKind::EventStart => 'meeting_reminders',
            ReminderKind::TaskDue => 'task_reminders',
            ReminderKind::PresentationPreparation, ReminderKind::PresentationReview => 'presentation_reminders',
        };
    }

    /**
     * Get the category of an audited activity.
     */
    public static function forActivity(string $action): string
    {
        return match (true) {
            in_array($action, ['event.rescheduled', 'event.cancelled'], true) => 'schedule_changes',
            in_array($action, ['task.assigned', 'task.reassigned'], true) => 'task_assignments',
            Str::startsWith($action, ['user.', 'role.', 'settings.']) => 'account_security',
            default => 'record_activity',
        };
    }

    /**
     * Determine whether a category's alerts may be gathered into the digest.
     */
    public static function digestible(string $category): bool
    {
        return self::DEFINITIONS[$category]['digestible'] ?? false;
    }
}
