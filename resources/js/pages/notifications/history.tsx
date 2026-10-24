import { Head, router } from '@inertiajs/react';
import { RotateCcw, Send } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import { humanize } from '@/lib/labels';
import { history } from '@/routes/notifications';
import { retry } from '@/routes/reminders';
import type {
    NotificationDelivery,
    Option,
    Paginated,
    UserOption,
} from '@/types';

type Filters = {
    channel: string;
    status: string;
    type: string;
    recipient: string;
    from: string;
    to: string;
};

type HistoryProps = {
    deliveries: Paginated<NotificationDelivery>;
    filters: Filters;
    options: {
        channels: Option[];
        statuses: Option[];
        types: Option[];
        users: UserOption[];
    };
    scope: 'all' | 'mine';
};

export default function NotificationHistory({
    deliveries,
    filters,
    options,
    scope,
}: HistoryProps) {
    const { timezone } = useAuth();

    const visit = (overrides: Partial<Filters>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...overrides }).filter(
                ([, value]) => value !== '',
            ),
        );
        router.get(
            history.url({ query }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Delivery history" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Delivery history"
                    description={
                        <>
                            Every delivery attempt for{' '}
                            {scope === 'all' ? 'all users' : 'you'}. “Sent”
                            means the provider accepted the message; it does not
                            guarantee the message reached the device or inbox.
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <NativeSelect
                        className="w-auto"
                        value={filters.type}
                        onChange={(e) => visit({ type: e.target.value })}
                        aria-label="Filter by type"
                    >
                        <option value="">All types</option>
                        {options.types.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-auto"
                        value={filters.channel}
                        onChange={(e) => visit({ channel: e.target.value })}
                        aria-label="Filter by channel"
                    >
                        <option value="">All channels</option>
                        {options.channels.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-auto"
                        value={filters.status}
                        onChange={(e) => visit({ status: e.target.value })}
                        aria-label="Filter by status"
                    >
                        <option value="">All statuses</option>
                        {options.statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                    {options.users.length > 0 && (
                        <NativeSelect
                            className="w-auto"
                            value={filters.recipient}
                            onChange={(e) =>
                                visit({ recipient: e.target.value })
                            }
                            aria-label="Filter by recipient"
                        >
                            <option value="">All recipients</option>
                            {options.users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                    <Input
                        type="date"
                        className="w-auto"
                        value={filters.from}
                        onChange={(e) => visit({ from: e.target.value })}
                        aria-label="From date"
                    />
                    <Input
                        type="date"
                        className="w-auto"
                        value={filters.to}
                        onChange={(e) => visit({ to: e.target.value })}
                        aria-label="To date"
                    />
                </div>

                {deliveries.data.length === 0 ? (
                    <EmptyState
                        icon={Send}
                        title="No delivery attempts match these filters"
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Notification
                                        </th>
                                        {scope === 'all' && (
                                            <th className="px-3 py-2 font-medium">
                                                Recipient
                                            </th>
                                        )}
                                        <th className="px-3 py-2 font-medium">
                                            Channel
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Scheduled
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Attempted
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Attempt
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Details
                                        </th>
                                        <th className="px-3 py-2">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {deliveries.data.map((delivery) => (
                                        <tr key={delivery.id}>
                                            <td className="px-3 py-2">
                                                {humanize(
                                                    delivery.notification_type,
                                                )}
                                            </td>
                                            {scope === 'all' && (
                                                <td className="px-3 py-2">
                                                    {delivery.recipient ?? '—'}
                                                </td>
                                            )}
                                            <td className="px-3 py-2">
                                                {delivery.channel_label}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                {delivery.scheduled_for
                                                    ? formatDateTime(
                                                          delivery.scheduled_for,
                                                          timezone,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    delivery.attempted_at,
                                                    timezone,
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                <StatusBadge
                                                    status={delivery.status}
                                                />
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {delivery.attempt_number}
                                            </td>
                                            <td className="max-w-xs px-3 py-2 text-xs text-muted-foreground">
                                                {delivery.error_message ??
                                                    (delivery.provider_message_id ? (
                                                        <span className="break-all">
                                                            ID:{' '}
                                                            {
                                                                delivery.provider_message_id
                                                            }
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    ))}
                                            </td>
                                            <td className="px-3 py-2">
                                                {delivery.can_retry &&
                                                    delivery.reminder_id && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    retry.url(
                                                                        delivery.reminder_id as number,
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <RotateCcw />
                                                            Retry
                                                        </Button>
                                                    )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <PaginationNav paginator={deliveries} />
                    </>
                )}
            </div>
        </>
    );
}

NotificationHistory.layout = {
    breadcrumbs: [{ title: 'Delivery history', href: history() }],
};
