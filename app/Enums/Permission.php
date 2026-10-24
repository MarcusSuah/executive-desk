<?php

namespace App\Enums;

enum Permission: string
{
    case ManageUsers = 'users.manage';
    case ManageSettings = 'settings.manage';
    case ViewAuditLogs = 'audit.view';
    case ManageCalendar = 'calendar.manage';
    case ManageTasks = 'tasks.manage';
    case ManagePresentations = 'presentations.manage';
    case ManageBriefings = 'briefings.manage';
    case ViewReports = 'reports.view';
    case ViewAllDeliveries = 'deliveries.view-all';
    case RetryDeliveries = 'deliveries.retry';
}
