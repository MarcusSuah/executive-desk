<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * How the activity log groups and grades audit entries.
 *
 * The module comes from the first part of the action ("event.created" → Calendar),
 * and the result from the action and, for requests recorded by the safety net, the
 * recorded outcome. The SQL expressions let the list, the statistics, the filters,
 * and the export all agree.
 */
class AuditCatalog
{
    public const RESULTS = ['success', 'warning', 'failed'];

    /**
     * Module labels by action prefix.
     *
     * @var array<string, string>
     */
    public const MODULES = [
        'event' => 'Calendar',
        'task' => 'Tasks',
        'presentation' => 'Presentations',
        'checklist' => 'Checklists',
        'attachment' => 'Files',
        'reminder' => 'Reminders',
        'briefing' => 'Briefings',
        'export' => 'Reports',
        'user' => 'Users',
        'role' => 'Roles',
        'settings' => 'Settings',
        'auth' => 'Sign-in',
        'security' => 'Account',
        'profile' => 'Account',
        'phone' => 'Notifications',
        'push' => 'Notifications',
        'notification_preferences' => 'Notifications',
        'inbox' => 'Notifications',
        'page' => 'Navigation',
        'request' => 'Requests',
    ];

    /**
     * Outcomes recorded for requests that count as failed or as a warning.
     *
     * @var array<int, string>
     */
    private const FAILED_OUTCOMES = ['error', 'denied', 'not found', 'session expired'];

    /**
     * @var array<int, string>
     */
    private const WARNING_OUTCOMES = ['rejected by validation', 'rate limited'];

    /**
     * Actions that are themselves failures or warnings.
     *
     * @var array<int, string>
     */
    private const FAILED_ACTIONS = ['auth.failed', 'auth.two_factor_failed', 'auth.google_rejected'];

    /**
     * @var array<int, string>
     */
    private const WARNING_ACTIONS = ['auth.lockout'];

    /**
     * Get the label of the module an action belongs to.
     */
    public static function module(string $action): string
    {
        $prefix = Str::before($action, '.');

        return self::MODULES[$prefix] ?? Str::headline($prefix);
    }

    /**
     * Get the action prefixes that belong to a module label.
     *
     * @return array<int, string>
     */
    public static function prefixesFor(string $module): array
    {
        return array_keys(array_filter(self::MODULES, fn (string $label) => $label === $module));
    }

    /**
     * Grade an entry as "success", "warning", or "failed".
     *
     * @param  array<string, mixed>|null  $newValues
     */
    public static function result(string $action, ?array $newValues): string
    {
        $outcome = is_string($newValues['outcome'] ?? null) ? $newValues['outcome'] : null;

        return match (true) {
            in_array($action, self::FAILED_ACTIONS, true), in_array($outcome, self::FAILED_OUTCOMES, true) => 'failed',
            in_array($action, self::WARNING_ACTIONS, true), in_array($outcome, self::WARNING_OUTCOMES, true) => 'warning',
            default => 'success',
        };
    }

    /**
     * The SQL expression that grades an entry, matching result(). Its placeholders
     * take resultBindings(), in order.
     */
    public const RESULT_SQL = 'CASE'
        ." WHEN action IN (?, ?, ?) OR JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.outcome')) IN (?, ?, ?, ?) THEN 'failed'"
        ." WHEN action IN (?) OR JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.outcome')) IN (?, ?) THEN 'warning'"
        ." ELSE 'success' END";

    /**
     * Get the values for the placeholders in RESULT_SQL.
     *
     * @return array<int, string>
     */
    public static function resultBindings(): array
    {
        return [...self::FAILED_ACTIONS, ...self::FAILED_OUTCOMES, ...self::WARNING_ACTIONS, ...self::WARNING_OUTCOMES];
    }
}
