<?php

namespace App\Enums;

/**
 * Everything the application checks access against.
 *
 * Permissions are defined in code because each one guards specific features.
 * Administrators decide which roles (and which individual users) hold them.
 */
enum Permission: string
{
    case ManageUsers = 'users.manage';
    case ManageRoles = 'roles.manage';
    case ManageSettings = 'settings.manage';
    case ViewAuditLogs = 'audit.view';
    case ManageCalendar = 'calendar.manage';
    case ManageTasks = 'tasks.manage';
    case ManagePresentations = 'presentations.manage';
    case ManageBriefings = 'briefings.manage';
    case ViewReports = 'reports.view';
    case ViewAllDeliveries = 'deliveries.view-all';
    case RetryDeliveries = 'deliveries.retry';

    /**
     * Get a short label for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => 'Manage users',
            self::ManageRoles => 'Manage roles & permissions',
            self::ManageSettings => 'Manage application settings',
            self::ViewAuditLogs => 'View the audit log',
            self::ManageCalendar => 'Manage all calendars',
            self::ManageTasks => 'Manage all tasks',
            self::ManagePresentations => 'Manage all presentations',
            self::ManageBriefings => 'Manage briefings for others',
            self::ViewReports => 'View reports',
            self::ViewAllDeliveries => 'View everyone’s delivery history',
            self::RetryDeliveries => 'Retry failed reminders',
        };
    }

    /**
     * Describe what the permission allows.
     */
    public function description(): string
    {
        return match ($this) {
            self::ManageUsers => 'Create, edit, and deactivate accounts, assign roles, and send password reset links.',
            self::ManageRoles => 'Create roles and choose which permissions each role and user has.',
            self::ManageSettings => 'Change notification defaults, Google sign-in, the sign-in background, and see delivery health.',
            self::ViewAuditLogs => 'See every recorded activity, including sign-ins and IP addresses.',
            self::ManageCalendar => 'See, create, reschedule, and cancel appointments on every executive’s calendar.',
            self::ManageTasks => 'See, create, assign, and edit every task.',
            self::ManagePresentations => 'See, create, edit, and delete every presentation.',
            self::ManageBriefings => 'Prepare and send briefings for other people and see their briefings.',
            self::ViewReports => 'Open reports and exports for the records the user can see.',
            self::ViewAllDeliveries => 'See notification delivery attempts for every user.',
            self::RetryDeliveries => 'Queue failed reminders for another delivery attempt.',
        };
    }

    /**
     * Get the area a permission belongs to, for grouping on screen.
     */
    public function group(): string
    {
        return match ($this) {
            self::ManageUsers, self::ManageRoles, self::ManageSettings, self::ViewAuditLogs => 'Administration',
            self::ManageCalendar, self::ManageTasks, self::ManagePresentations, self::ManageBriefings => 'Executive support',
            self::ViewReports, self::ViewAllDeliveries, self::RetryDeliveries => 'Reporting & delivery',
        };
    }
}
