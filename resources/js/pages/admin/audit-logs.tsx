import { Head, router } from '@inertiajs/react';
import {
    Activity,
    CircleAlert,
    Columns3,
    Download,
    LayoutGrid,
    ListFilter,
    ScrollText,
    Search,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import StatCard from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { useInitials } from '@/hooks/use-initials';
import { formatDate, formatTime } from '@/lib/datetime';
import { auditLabel, humanize } from '@/lib/labels';
import { cn } from '@/lib/utils';
import { exportMethod, index } from '@/routes/admin/audit-logs';
import type { AuditEntry, Option, Paginated, UserOption } from '@/types';

type Filters = {
    q: string;
    module: string;
    user: string;
    result: string;
    from: string;
    to: string;
    per_page: number;
};

type Stats = {
    total_today: number;
    failed_today: number;
    most_active_module: string | null;
    most_active_count: number;
};

type OptionalColumn = 'changes' | 'ip';

const OPTIONAL_COLUMNS: { key: OptionalColumn; label: string }[] = [
    { key: 'changes', label: 'Changes' },
    { key: 'ip', label: 'IP address' },
];

const COLUMNS_STORAGE_KEY = 'activity-log-columns';

const RESULT_LABELS: Record<AuditEntry['result'], string> = {
    success: 'Success',
    warning: 'Warning',
    failed: 'Failed',
};

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

function readColumns(): OptionalColumn[] {
    try {
        const stored = JSON.parse(
            window.localStorage.getItem(COLUMNS_STORAGE_KEY) ?? '[]',
        ) as unknown;

        return Array.isArray(stored)
            ? OPTIONAL_COLUMNS.map((column) => column.key).filter((key) =>
                  stored.includes(key),
              )
            : [];
    } catch {
        return [];
    }
}

export default function AuditLogs({
    entries,
    stats,
    filters,
    modules,
    users,
    perPageOptions,
    maxExportRows,
}: {
    entries: Paginated<AuditEntry>;
    stats: Stats;
    filters: Filters;
    modules: Option[];
    users: UserOption[];
    perPageOptions: number[];
    maxExportRows: number;
}) {
    const { timezone } = useAuth();
    const getInitials = useInitials();
    const [search, setSearch] = useState(filters.q);
    const [showFilters, setShowFilters] = useState(
        filters.module !== '' || filters.user !== '' || filters.result !== '',
    );
    const [columns, setColumns] = useState<OptionalColumn[]>([]);

    useEffect(() => setColumns(readColumns()), []);

    const toggleColumn = (key: OptionalColumn, visible: boolean) => {
        const next = visible
            ? [...columns, key]
            : columns.filter((column) => column !== key);
        setColumns(next);

        try {
            window.localStorage.setItem(
                COLUMNS_STORAGE_KEY,
                JSON.stringify(next),
            );
        } catch {
            // Column choices are a convenience; ignore blocked storage.
        }
    };

    const query = (overrides: Partial<Filters> = {}) =>
        Object.fromEntries(
            Object.entries({ ...filters, ...overrides }).filter(
                ([key, value]) =>
                    value !== '' && !(key === 'per_page' && value === 10),
            ),
        );

    const visit = (overrides: Partial<Filters>) =>
        router.get(
            index.url({ query: query(overrides) }),
            {},
            { preserveState: true, preserveScroll: true },
        );

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search.trim() });
    };

    const exportUrl = (format: 'csv' | 'xlsx') =>
        exportMethod.url({ query: { ...query({ per_page: 10 }), format } });

    const hasFilters =
        filters.q !== '' ||
        filters.module !== '' ||
        filters.user !== '' ||
        filters.result !== '' ||
        filters.from !== '' ||
        filters.to !== '';

    return (
        <>
            <Head title="Activity log" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Activity log"
                    description="A read-only record of who did what, and when. Passwords, tokens, and push keys are never recorded."
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard
                        label="Total events today"
                        value={stats.total_today}
                        icon={Activity}
                        tone="info"
                    />
                    <StatCard
                        label="Failed actions today"
                        value={stats.failed_today}
                        icon={CircleAlert}
                        tone={stats.failed_today > 0 ? 'danger' : 'neutral'}
                    />
                    <StatCard
                        label="Most active module"
                        value={stats.most_active_module ?? '—'}
                        icon={LayoutGrid}
                        tone="success"
                        hint={
                            stats.most_active_module
                                ? `${stats.most_active_count} event(s) today`
                                : 'No activity yet today'
                        }
                    />
                </div>

                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center">
                        <form
                            onSubmit={submitSearch}
                            className="relative min-w-0 flex-1"
                            role="search"
                        >
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                type="search"
                                className="pl-9"
                                placeholder="Search by user, action, or IP address"
                                aria-label="Search activity"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onBlur={() =>
                                    search.trim() !== filters.q &&
                                    visit({ q: search.trim() })
                                }
                            />
                        </form>
                        <div className="flex flex-wrap items-center gap-2">
                            <Input
                                type="date"
                                className="w-auto"
                                value={filters.from}
                                max={filters.to || undefined}
                                onChange={(e) =>
                                    visit({ from: e.target.value })
                                }
                                aria-label="From date"
                            />
                            <span className="text-sm text-muted-foreground">
                                to
                            </span>
                            <Input
                                type="date"
                                className="w-auto"
                                value={filters.to}
                                min={filters.from || undefined}
                                onChange={(e) => visit({ to: e.target.value })}
                                aria-label="To date"
                            />
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        disabled={entries.meta.total === 0}
                                    >
                                        <Download />
                                        Export
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                        Matching events, newest first (up to{' '}
                                        {maxExportRows.toLocaleString()})
                                    </DropdownMenuLabel>
                                    <DropdownMenuItem asChild>
                                        <a href={exportUrl('csv')}>CSV</a>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <a href={exportUrl('xlsx')}>
                                            Excel (.xlsx)
                                        </a>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Filters"
                                aria-expanded={showFilters}
                                className={cn(showFilters && 'bg-accent')}
                                onClick={() => setShowFilters(!showFilters)}
                            >
                                <ListFilter />
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        aria-label="Columns"
                                    >
                                        <Columns3 />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel>
                                        Extra columns
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {OPTIONAL_COLUMNS.map((column) => (
                                        <DropdownMenuCheckboxItem
                                            key={column.key}
                                            checked={columns.includes(
                                                column.key,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleColumn(
                                                    column.key,
                                                    checked === true,
                                                )
                                            }
                                            onSelect={(event) =>
                                                event.preventDefault()
                                            }
                                        >
                                            {column.label}
                                        </DropdownMenuCheckboxItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    {showFilters && (
                        <div className="flex flex-wrap items-center gap-2 border-b bg-muted/30 px-4 py-3">
                            <NativeSelect
                                className="w-auto"
                                value={filters.module}
                                onChange={(e) =>
                                    visit({ module: e.target.value })
                                }
                                aria-label="Filter by module"
                            >
                                <option value="">All modules</option>
                                {modules.map((module) => (
                                    <option
                                        key={module.value}
                                        value={module.value}
                                    >
                                        {module.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <NativeSelect
                                className="w-auto"
                                value={filters.user}
                                onChange={(e) =>
                                    visit({ user: e.target.value })
                                }
                                aria-label="Filter by user"
                            >
                                <option value="">All users</option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            <NativeSelect
                                className="w-auto"
                                value={filters.result}
                                onChange={(e) =>
                                    visit({ result: e.target.value })
                                }
                                aria-label="Filter by result"
                            >
                                <option value="">All results</option>
                                {Object.entries(RESULT_LABELS).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </NativeSelect>
                            <NativeSelect
                                className="w-auto"
                                value={filters.per_page}
                                onChange={(e) =>
                                    visit({ per_page: Number(e.target.value) })
                                }
                                aria-label="Rows per page"
                            >
                                {perPageOptions.map((size) => (
                                    <option key={size} value={size}>
                                        {size} per page
                                    </option>
                                ))}
                            </NativeSelect>
                            {hasFilters && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setSearch('');
                                        visit({
                                            q: '',
                                            module: '',
                                            user: '',
                                            result: '',
                                            from: '',
                                            to: '',
                                        });
                                    }}
                                >
                                    Clear filters
                                </Button>
                            )}
                        </div>
                    )}

                    {entries.data.length === 0 ? (
                        <div className="p-6">
                            <EmptyState
                                icon={ScrollText}
                                title="No events match these filters"
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[860px] text-sm">
                                <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2.5">
                                            Date & time
                                        </th>
                                        <th className="px-4 py-2.5">User</th>
                                        <th className="px-4 py-2.5">Action</th>
                                        <th className="px-4 py-2.5">Module</th>
                                        <th className="px-4 py-2.5">Record</th>
                                        {columns.includes('changes') && (
                                            <th className="px-4 py-2.5">
                                                Changes
                                            </th>
                                        )}
                                        {columns.includes('ip') && (
                                            <th className="px-4 py-2.5">
                                                IP address
                                            </th>
                                        )}
                                        <th className="px-4 py-2.5">Result</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y align-top">
                                    {entries.data.map((entry) => (
                                        <tr
                                            key={entry.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className="block">
                                                    {formatDate(
                                                        entry.created_at,
                                                        timezone,
                                                    )}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatTime(
                                                        entry.created_at,
                                                        timezone,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="flex items-center gap-2">
                                                    <span
                                                        aria-hidden
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                                    >
                                                        {entry.actor
                                                            ? getInitials(
                                                                  entry.actor,
                                                              )
                                                            : 'SY'}
                                                    </span>
                                                    <span className="whitespace-nowrap">
                                                        {entry.actor ??
                                                            'System'}
                                                    </span>
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                {auditLabel(entry.action)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex rounded-full border bg-muted/60 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap">
                                                    {entry.module}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {entry.subject && (
                                                    <span className="block text-foreground">
                                                        {entry.subject}
                                                    </span>
                                                )}
                                                {entry.auditable_type
                                                    ? `${humanize(entry.auditable_type)} #${entry.auditable_id}`
                                                    : '—'}
                                            </td>
                                            {columns.includes('changes') && (
                                                <td className="max-w-md px-4 py-3 text-xs text-muted-foreground">
                                                    {changes(entry).map(
                                                        (line) => (
                                                            <span
                                                                key={line}
                                                                className="block wrap-break-word"
                                                            >
                                                                {line}
                                                            </span>
                                                        ),
                                                    )}
                                                </td>
                                            )}
                                            {columns.includes('ip') && (
                                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                                    {entry.ip_address ?? '—'}
                                                </td>
                                            )}
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={entry.result}
                                                    label={
                                                        RESULT_LABELS[
                                                            entry.result
                                                        ]
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {entries.meta.total > 0 && (
                        <div className="border-t px-4 py-3">
                            <PaginationNav paginator={entries} noun="events" />
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

AuditLogs.layout = {
    breadcrumbs: [{ title: 'Activity log', href: index() }],
};
