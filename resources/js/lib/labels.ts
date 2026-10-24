/**
 * Display labels and colour classes for statuses and priorities.
 */

export function humanize(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return value
        .replace(/[._]/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export const priorityClasses: Record<string, string> = {
    low: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    normal: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    high: 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    urgent: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
};

export const statusClasses: Record<string, string> = {
    scheduled: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    pending:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    queued: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    processing:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    in_progress:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    in_preparation:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    under_review:
        'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    completed:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    approved:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    delivered:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    sent: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    generated: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    partially_delivered:
        'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    failed: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    overdue: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    unavailable:
        'bg-orange-50 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    skipped:
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    disabled:
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    cancelled:
        'bg-slate-100 text-slate-500 line-through dark:bg-slate-800 dark:text-slate-400',
    active: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    inactive:
        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

export const eventTypeClasses: Record<string, string> = {
    internal_meeting: 'border-l-sky-500 bg-sky-50 dark:bg-sky-950/60',
    external_meeting: 'border-l-violet-500 bg-violet-50 dark:bg-violet-950/60',
    business_appointment: 'border-l-teal-500 bg-teal-50 dark:bg-teal-950/60',
    interview: 'border-l-fuchsia-500 bg-fuchsia-50 dark:bg-fuchsia-950/60',
    presentation: 'border-l-amber-500 bg-amber-50 dark:bg-amber-950/60',
    travel: 'border-l-emerald-500 bg-emerald-50 dark:bg-emerald-950/60',
    personal: 'border-l-rose-500 bg-rose-50 dark:bg-rose-950/60',
    deadline: 'border-l-red-600 bg-red-50 dark:bg-red-950/60',
};

export const auditActionLabels: Record<string, string> = {
    'event.created': 'Appointment created',
    'event.updated': 'Appointment updated',
    'event.rescheduled': 'Appointment rescheduled',
    'event.cancelled': 'Appointment cancelled',
    'event.completed': 'Appointment completed',
    'event.deleted': 'Appointment deleted',
    'task.created': 'Task created',
    'task.assigned': 'Task assigned',
    'task.reassigned': 'Task reassigned',
    'task.updated': 'Task updated',
    'task.completed': 'Task completed',
    'task.reopened': 'Task reopened',
    'task.cancelled': 'Task cancelled',
    'task.status_changed': 'Task status changed',
    'task.occurrence_generated': 'Recurring occurrence generated',
    'presentation.created': 'Presentation created',
    'presentation.updated': 'Presentation updated',
    'presentation.approved': 'Presentation approved',
    'checklist.item_added': 'Checklist item added',
    'checklist.item_completed': 'Checklist item completed',
    'checklist.item_reopened': 'Checklist item reopened',
    'checklist.item_removed': 'Checklist item removed',
    'attachment.uploaded': 'File uploaded',
    'attachment.downloaded': 'File downloaded',
    'attachment.deleted': 'File removed',
    'reminder.disabled': 'Reminder turned off',
    'reminder.enabled': 'Reminder turned on',
    'reminder.retried': 'Reminder retried',
    'auth.login': 'Signed in',
    'auth.logout': 'Signed out',
    'auth.failed': 'Failed sign-in attempt',
    'auth.lockout': 'Locked out after too many attempts',
    'auth.password_reset': 'Password reset',
    'auth.password_reset_link_sent': 'Password reset link requested',
    'auth.email_verified': 'Email address verified',
    'auth.two_factor_failed': 'Incorrect two-factor code',
    'auth.recovery_code_used': 'Signed in with a recovery code',
    'auth.google_linked': 'Google account linked',
    'auth.google_rejected': 'Google sign-in refused',
    'security.password_changed': 'Password changed',
    'security.two_factor_enabled': 'Two-factor authentication turned on',
    'security.two_factor_confirmed': 'Two-factor authentication confirmed',
    'security.two_factor_disabled': 'Two-factor authentication turned off',
    'security.recovery_codes_generated': 'Recovery codes regenerated',
    'profile.updated': 'Profile updated',
    'page.viewed': 'Page viewed',
    'user.created': 'Account created',
    'user.updated': 'Account updated',
    'user.roles_changed': 'Roles changed',
    'user.deactivated': 'Account deactivated',
    'user.reactivated': 'Account reactivated',
    'user.password_reset_sent': 'Password reset link sent by administrator',
    'user.deleted': 'Account deleted',
    'user.permissions_changed': 'Individual permissions changed',
    'role.created': 'Role created',
    'role.updated': 'Role changed',
    'role.deleted': 'Role deleted',
    'settings.updated': 'Notification defaults changed',
    'settings.google_sign_in_updated': 'Google sign-in settings changed',
    'settings.google_sign_in_cleared': 'Google sign-in settings removed',
    'settings.sign_in_background_updated': 'Sign-in background changed',
    'settings.sign_in_background_removed': 'Sign-in background reset',
    'notification_preferences.updated': 'Notification preferences changed',
    'phone.code_sent': 'Phone verification code sent',
    'phone.verified': 'Phone number verified',
    'phone.removed': 'Phone number removed',
    'push.subscribed': 'Push notifications enabled on a device',
    'push.unsubscribed': 'Push device removed',
    'briefing.generated': 'Briefing prepared',
    'briefing.sent': 'Briefing sent',
    'export.report': 'Report exported',
    'export.briefing_pdf': 'Briefing downloaded as PDF',
};

export function auditLabel(action: string): string {
    if (auditActionLabels[action]) {
        return auditActionLabels[action];
    }

    // Requests recorded by the audit safety net, e.g. "request.inbox.read".
    if (action.startsWith('request.')) {
        return `Request: ${humanize(action.slice('request.'.length))}`;
    }

    return humanize(action);
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
