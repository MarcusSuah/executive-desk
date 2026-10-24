import {
    Bell,
    CalendarClock,
    CalendarDays,
    CalendarX2,
    CheckSquare,
    FileText,
    Presentation,
    Settings2,
    UserCog,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const ICONS: Record<string, LucideIcon> = {
    event_reminder: CalendarClock,
    schedule_change: CalendarX2,
    task_reminder: CheckSquare,
    task_assigned: CheckSquare,
    presentation_reminder: Presentation,
    briefing: FileText,
    activity_calendar_event: CalendarDays,
    activity_task: CheckSquare,
    activity_presentation: Presentation,
    activity_executive_briefing: FileText,
    activity_user: UserCog,
    activity_settings: Settings2,
};

/**
 * Get the icon for a notification kind.
 */
export function notificationIcon(kind: string): LucideIcon {
    return ICONS[kind] ?? Bell;
}
