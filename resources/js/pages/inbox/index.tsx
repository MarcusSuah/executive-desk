import { Head, router } from '@inertiajs/react';
import { BellOff, CheckCheck } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import { notificationIcon } from '@/lib/notification-icons';
import { cn } from '@/lib/utils';
import { index, read, readAll } from '@/routes/inbox';
import type { PaginationLink } from '@/types';

type InboxNotification = {
    id: string;
    kind: string;
    title: string;
    body: string;
    has_link: boolean;
    read: boolean;
    created_at: string | null;
};

type InboxProps = {
    notifications: {
        data: InboxNotification[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filter: 'all' | 'unread';
    unread: number;
};

export default function Inbox({ notifications, filter, unread }: InboxProps) {
    const { timezone } = useAuth();

    return (
        <>
            <Head title="Notifications" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Notifications"
                    description="Reminders, briefings, and activity on the appointments, tasks, and presentations you are involved in."
                    actions={
                        unread > 0 && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        readAll.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCheck />
                                Mark all as read
                            </Button>
                        )
                    }
                />

                <div
                    className="inline-flex w-fit rounded-md border p-0.5"
                    role="group"
                    aria-label="Show"
                >
                    {(['all', 'unread'] as const).map((value) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={filter === value}
                            onClick={() =>
                                router.get(
                                    index.url({
                                        query:
                                            value === 'all'
                                                ? {}
                                                : { filter: value },
                                    }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className={cn(
                                'rounded px-3 py-1 text-sm',
                                filter === value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                            )}
                        >
                            {value === 'all' ? 'All' : `Unread (${unread})`}
                        </button>
                    ))}
                </div>

                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={BellOff}
                        title={
                            filter === 'unread'
                                ? 'No unread notifications'
                                : 'No notifications yet'
                        }
                    />
                ) : (
                    <ul className="divide-y overflow-hidden rounded-xl border">
                        {notifications.data.map((item) => {
                            const Icon = notificationIcon(item.kind);

                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(
                                                read.url(item.id),
                                                {},
                                                {
                                                    preserveScroll:
                                                        !item.has_link,
                                                },
                                            )
                                        }
                                        className={cn(
                                            'flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-accent/40',
                                            {
                                                'bg-sky-50/70 dark:bg-sky-950/40':
                                                    !item.read,
                                            },
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full',
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
                                                    'block text-sm',
                                                    item.read
                                                        ? 'font-normal'
                                                        : 'font-semibold',
                                                )}
                                            >
                                                {item.title}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {item.body}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {formatDateTime(
                                                item.created_at,
                                                timezone,
                                            )}
                                        </span>
                                        {!item.read && (
                                            <span
                                                className="mt-1.5 size-2 shrink-0 rounded-full bg-sky-600"
                                                aria-label="Unread"
                                            />
                                        )}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {notifications.links.length > 3 && (
                    <nav
                        className="flex flex-wrap gap-1"
                        aria-label="Pagination"
                    >
                        {notifications.links.map((link, position) =>
                            link.url ? (
                                <button
                                    key={position}
                                    type="button"
                                    onClick={() =>
                                        router.get(
                                            link.url as string,
                                            {},
                                            {
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                    className={cn(
                                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm',
                                        link.active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-accent',
                                    )}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                >
                                    {link.label
                                        .replace('&laquo;', '‹')
                                        .replace('&raquo;', '›')}
                                </button>
                            ) : null,
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

Inbox.layout = {
    breadcrumbs: [{ title: 'Notifications', href: index() }],
};
