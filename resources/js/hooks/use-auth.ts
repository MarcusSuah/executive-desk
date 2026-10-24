import { usePage } from '@inertiajs/react';

export type Permission =
    | 'users.manage'
    | 'roles.manage'
    | 'settings.manage'
    | 'audit.view'
    | 'calendar.manage'
    | 'tasks.manage'
    | 'presentations.manage'
    | 'briefings.manage'
    | 'reports.view'
    | 'deliveries.view-all'
    | 'deliveries.retry';

/**
 * Access the signed-in user, their time zone, and their permissions.
 */
export function useAuth() {
    const { auth } = usePage().props;

    return {
        user: auth.user,
        timezone: auth.user?.timezone ?? 'UTC',
        roles: auth.roles,
        can: (permission: Permission): boolean =>
            auth.permissions.includes(permission),
        hasRole: (role: string): boolean => auth.roles.includes(role),
    };
}
