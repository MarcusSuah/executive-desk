import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, Sparkles } from 'lucide-react';
import type { FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useAuth } from '@/hooks/use-auth';
import { formatDateKey, formatDateTime } from '@/lib/datetime';
import { humanize } from '@/lib/labels';
import { generate, index, show } from '@/routes/briefings';
import type { Briefing, Paginated, UserOption } from '@/types';

type Filters = { type: string; user: string };

export default function BriefingsIndex({
    briefings,
    filters,
    recipients,
}: {
    briefings: Paginated<Briefing>;
    filters: Filters;
    recipients: UserOption[];
}) {
    const { user, timezone } = useAuth();
    const form = useForm({
        type: 'daily',
        user_id: String(user.id),
    });

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

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(generate.url());
    };

    return (
        <>
            <Head title="Briefings" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Briefings"
                    description="Daily and weekly summaries built from the live schedule, tasks, and deadlines."
                />

                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-3"
                >
                    <span className="text-sm font-medium">Prepare now:</span>
                    <NativeSelect
                        className="w-auto"
                        value={form.data.type}
                        onChange={(e) => form.setData('type', e.target.value)}
                        aria-label="Briefing type"
                    >
                        <option value="daily">Today's briefing</option>
                        <option value="weekly">
                            Weekly briefing (next 7 days)
                        </option>
                    </NativeSelect>
                    {recipients.length > 0 && (
                        <NativeSelect
                            className="w-auto"
                            value={form.data.user_id}
                            onChange={(e) =>
                                form.setData('user_id', e.target.value)
                            }
                            aria-label="Prepare for"
                        >
                            {recipients.map((recipient) => (
                                <option key={recipient.id} value={recipient.id}>
                                    {recipient.name}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <Spinner /> : <Sparkles />}
                        Prepare
                    </Button>
                    <span className="text-xs text-muted-foreground">
                        Preparing does not send anything.
                    </span>
                </form>

                <div className="flex flex-wrap gap-2">
                    <NativeSelect
                        className="w-auto"
                        value={filters.type}
                        onChange={(e) => visit({ type: e.target.value })}
                        aria-label="Filter by type"
                    >
                        <option value="">All briefings</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </NativeSelect>
                    {recipients.length > 0 && (
                        <NativeSelect
                            className="w-auto"
                            value={filters.user}
                            onChange={(e) => visit({ user: e.target.value })}
                            aria-label="Filter by recipient"
                        >
                            <option value="">Everyone</option>
                            {recipients.map((recipient) => (
                                <option key={recipient.id} value={recipient.id}>
                                    {recipient.name}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                </div>

                {briefings.data.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="No briefings yet"
                        description="Briefings are generated at the time set in notification settings, or prepare one now."
                    />
                ) : (
                    <>
                        <ul className="divide-y rounded-xl border">
                            {briefings.data.map((briefing) => (
                                <li key={briefing.id}>
                                    <Link
                                        href={show(briefing.id)}
                                        className="flex flex-col gap-1 px-4 py-3 hover:bg-accent/40 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-medium">
                                                {humanize(briefing.type)}{' '}
                                                briefing ·{' '}
                                                {formatDateKey(
                                                    briefing.period_start,
                                                    {
                                                        weekday: 'short',
                                                        month: 'short',
                                                        day: 'numeric',
                                                        year: 'numeric',
                                                    },
                                                )}
                                                {briefing.period_end !==
                                                    briefing.period_start &&
                                                    ` – ${formatDateKey(briefing.period_end, { month: 'short', day: 'numeric' })}`}
                                            </span>
                                            <span className="block truncate text-sm text-muted-foreground">
                                                {briefing.user &&
                                                    recipients.length > 0 &&
                                                    `${briefing.user.name} · `}
                                                {briefing.content.headline}
                                            </span>
                                        </span>
                                        <span className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                                            {formatDateTime(
                                                briefing.generated_at,
                                                timezone,
                                            )}
                                            <StatusBadge
                                                status={briefing.status}
                                            />
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <PaginationNav paginator={briefings} />
                    </>
                )}
            </div>
        </>
    );
}

BriefingsIndex.layout = {
    breadcrumbs: [{ title: 'Briefings', href: index() }],
};
