<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Role: string
{
    use HasOptions;

    case Administrator = 'administrator';
    case Assistant = 'assistant';
    case Executive = 'executive';

    /**
     * Get the display name of the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'System Administrator',
            self::Assistant => 'Executive Assistant',
            self::Executive => 'Executive',
        };
    }

    /**
     * Describe what the built-in role is for.
     */
    public function description(): string
    {
        return match ($this) {
            self::Administrator => 'Runs the system: accounts, roles, settings, audit log, and delivery health.',
            self::Assistant => 'Manages executives’ calendars, tasks, presentations, and briefings.',
            self::Executive => 'Has a calendar. Appointments and presentations belong to executives.',
        };
    }

    /**
     * Get the permissions granted to the role when it is first created.
     * Administrators can change them afterwards on the Roles & permissions page.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrator => [
                Permission::ManageUsers,
                Permission::ManageRoles,
                Permission::ManageSettings,
                Permission::ViewAuditLogs,
                Permission::ViewReports,
                Permission::ViewAllDeliveries,
                Permission::RetryDeliveries,
            ],
            self::Assistant => [
                Permission::ManageCalendar,
                Permission::ManageTasks,
                Permission::ManagePresentations,
                Permission::ManageBriefings,
                Permission::ViewReports,
                Permission::ViewAllDeliveries,
                Permission::RetryDeliveries,
            ],
            self::Executive => [
                Permission::ViewReports,
            ],
        };
    }
}
