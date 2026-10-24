import { Head, router } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import { auditLabel, humanize } from '@/lib/labels';
import { index } from '@/routes/admin/audit-logs';
import type { AuditEntry, Option, Paginated, UserOption } from '@/types';

type Filters = { area: string; user: string; from: string; to: string };

/**
 * Render changed values as "field: old → new" lines.
 */
function changes(entry: AuditEntry): string[] {
    const keys = new Set([
        ...Object.keys(entry.old_values ?? {}),
        ...Object.keys(entry.new_values ?? {}),
    ]);
    const format = (value: unknown): string =>
        value === null || value === undefined
            ? '∅'
            : typeof value === 'object'
              ? JSON.stringify(value)
              : String(value as string | number | boolean);

    return [...keys].slice(0, 6).map((key) => {
        const before = entry.old_values?.[key];
        const after = entry.new_values?.[key];

        return before === undefined
            ? `${humanize(key)}: ${format(after)}`
            : `${humanize(key)}: ${format(before)} → ${format(after)}`;
    });
}

export default function AuditLogs({
    entries,
    filters,
    areas,
    users,
}: {
    entries: Paginated<AuditEntry>;
    filters: Filters;
    areas: Option[];
    users: UserOption[];
}) {
    const { timezone } = useAuth();

    const visit = (overrides: Partial<Filters>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...overrides }).filter(
                ([, value]) => value !== '',
            ),
        );
        router.get(
            index.url({ query }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Audit log" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Audit log"
                    description="Who did what, and when. Passwords, tokens, and push keys are never recorded."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <NativeSelect
                        className="w-auto"
                        value={filters.area}
                        onChange={(e) => visit({ area: e.target.value })}
                        aria-label="Filter by area"
                    >
                        <option value="">All areas</option>
                        {areas.map((area) => (
                            <option key={area.value} value={area.value}>
                                {area.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-auto"
                        value={filters.user}
                        onChange={(e) => visit({ user: e.target.value })}
                        aria-label="Filter by user"
                    >
                        <option value="">Anyone</option>
                        {users.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name}
                            </option>
                        ))}
                    </NativeSelect>
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

                {entries.data.length === 0 ? (
                    <EmptyState
                        icon={ScrollText}
                        title="No audit entries match these filters"
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            When
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Who
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Action
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Record
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Changes
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            IP address
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y align-top">
                                    {entries.data.map((entry) => (
                                        <tr key={entry.id}>
                                            <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    entry.created_at,
                                                    timezone,
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {entry.actor ?? 'System'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {auditLabel(entry.action)}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {entry.auditable_type
                                                    ? `${humanize(entry.auditable_type)} #${entry.auditable_id}`
                                                    : '—'}
                                                {entry.subject && (
                                                    <span className="block text-foreground">
                                                        {entry.subject}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="max-w-md px-3 py-2 text-xs text-muted-foreground">
                                                {changes(entry).map((line) => (
                                                    <span
                                                        key={line}
                                                        className="block break-words"
                                                    >
                                                        {line}
                                                    </span>
                                                ))}
                                            </td>
                                            <td className="px-3 py-2 text-xs text-muted-foreground">
                                                {entry.ip_address ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <PaginationNav paginator={entries} />
                    </>
                )}
            </div>
        </>
    );
}

AuditLogs.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
