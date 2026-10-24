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
     * Get the permissions granted to the role.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrator => [
                Permission::ManageUsers,
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
