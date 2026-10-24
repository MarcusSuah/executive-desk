import { Link, router, usePage, usePoll } from '@inertiajs/react';
import {
    Bell,
    CalendarClock,
    CalendarX2,
    CheckSquare,
    FileText,
    Presentation,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { read, readAll } from '@/routes/inbox';
import { edit as notificationSettings } from '@/routes/notifications';

const ICONS: Record<string, typeof Bell> = {
    event_reminder: CalendarClock,
    schedule_change: CalendarX2,
    task_reminder: CheckSquare,
    task_assigned: CheckSquare,
    presentation_reminder: Presentation,
    briefing: FileText,
};

/**
 * The notification bell: unread count, the latest items, and mark-as-read actions.
 */
export function NotificationBell() {
    const { inbox } = usePage().props;
    const { timezone } = useAuth();

    // Refresh only the bell every minute so new reminders appear without a reload.
    usePoll(60_000, { only: ['inbox'] });

    if (!inbox) {
        return null;
    }

    const unread = inbox.unread;
    const badge = unread > 99 ? '99+' : String(unread);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-9"
                    aria-label={
                        unread > 0
                            ? `Notifications, ${unread} unread`
                            : 'Notifications'
                    }
                >
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-semibold text-white ring-2 ring-background">
                            {badge}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <DropdownMenuLabel className="flex items-center justify-between px-3 py-2.5">
                    <span className="text-sm font-semibold">Notifications</span>
                    {unread > 0 && (
                        <button
                            type="button"
                            className="text-xs font-medium text-sky-700 hover:underline dark:text-sky-300"
                            onClick={() =>
                                router.post(
                                    readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Mark all as read
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="m-0" />
                {inbox.items.length === 0 ? (
                    <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                        You're all caught up.
                    </p>
                ) : (
                    <div className="max-h-96 overflow-y-auto">
                        {inbox.items.map((item) => {
                            const Icon = ICONS[item.kind] ?? Bell;

                            return (
                                <DropdownMenuItem
                                    key={item.id}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-none px-3 py-2.5',
                                        {
                                            'bg-sky-50/70 dark:bg-sky-950/40':
                                                !item.read,
                                        },
                                    )}
                                    onSelect={() =>
                                        router.post(read.url(item.id))
                                    }
                                >
                                    <span
                                        className={cn(
                                            'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
                                            item.read
                                                ? 'bg-muted text-muted-foreground'
                                                : 'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-200',
                                        )}
                                    >
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={cn(
                                                'block truncate text-sm',
                                                item.read
                                                    ? 'font-normal'
                                                    : 'font-semibold',
                                            )}
                                        >
                                            {item.title}
                                        </span>
                                        <span className="line-clamp-2 block text-xs text-muted-foreground">
                                            {item.body}
                                        </span>
                                        {item.created_at && (
                                            <span className="block text-[11px] text-muted-foreground">
                                                {formatDateTime(
                                                    item.created_at,
                                                    timezone,
                                                )}
                                            </span>
                                        )}
                                    </span>
                                    {!item.read && (
                                        <span
                                            className="mt-1.5 size-2 shrink-0 rounded-full bg-sky-600"
                                            aria-label="Unread"
                                        />
                                    )}
                                </DropdownMenuItem>
                            );
                        })}
                    </div>
                )}
                <DropdownMenuSeparator className="m-0" />
                <DropdownMenuItem
                    asChild
                    className="justify-center rounded-none py-2 text-xs"
                >
                    <Link href={notificationSettings()}>
                        Notification settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
