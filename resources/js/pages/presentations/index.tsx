import { Head, Link, router } from '@inertiajs/react';
import {
    Paperclip,
    Plus,
    Presentation as PresentationIcon,
    Search,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/presentations';
import type { Option, Paginated, Presentation } from '@/types';

type Filters = { q: string; status: string; scope: string };

export default function PresentationsIndex({
    presentations,
    filters,
    statuses,
    canCreate,
}: {
    presentations: Paginated<Presentation>;
    filters: Filters;
    statuses: Option[];
    canCreate: boolean;
}) {
    const { timezone } = useAuth();
    const [search, setSearch] = useState(filters.q);

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

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search });
    };

    return (
        <>
            <Head title="Presentations" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Presentations"
                    description="Speaking engagements, preparation deadlines, slides, and approvals."
                    actions={
                        canCreate && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus />
                                    New presentation
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={submitSearch} className="relative">
                        <Search
                            className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                            aria-hidden
                        />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search presentations"
                            className="w-64 pl-8"
                            aria-label="Search presentations"
                        />
                    </form>
                    <NativeSelect
                        className="w-auto"
                        value={filters.status}
                        onChange={(e) => visit({ status: e.target.value })}
                        aria-label="Filter by status"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-auto"
                        value={filters.scope}
                        onChange={(e) => visit({ scope: e.target.value })}
                        aria-label="Time range"
                    >
                        <option value="upcoming">Upcoming</option>
                        <option value="past">Past</option>
                        <option value="all">All</option>
                    </NativeSelect>
                </div>

                {presentations.data.length === 0 ? (
                    <EmptyState
                        icon={PresentationIcon}
                        title="No presentations found"
                        description="Presentations you prepare or deliver will appear here."
                    />
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {presentations.data.map((presentation) => {
                                const deadlinePassed =
                                    presentation.preparation_deadline_at !==
                                        null &&
                                    new Date(
                                        presentation.preparation_deadline_at,
                                    ).getTime() < Date.now() &&
                                    ['draft', 'in_preparation'].includes(
                                        presentation.status,
                                    );

                                return (
                                    <Link
                                        key={presentation.id}
                                        href={show(presentation.id)}
                                        className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent/40"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <h2 className="font-semibold">
                                                {presentation.title}
                                            </h2>
                                            <StatusBadge
                                                status={presentation.status}
                                            />
                                        </div>
                                        <dl className="space-y-1 text-sm">
                                            <div className="flex justify-between gap-2">
                                                <dt className="text-muted-foreground">
                                                    Presentation
                                                </dt>
                                                <dd className="text-right">
                                                    {formatDateTime(
                                                        presentation.presentation_at,
                                                        timezone,
                                                    )}
                                                </dd>
                                            </div>
                                            <div className="flex justify-between gap-2">
                                                <dt className="text-muted-foreground">
                                                    Preparation due
                                                </dt>
                                                <dd
                                                    className={cn(
                                                        'text-right',
                                                        {
                                                            'font-medium text-red-600 dark:text-red-400':
                                                                deadlinePassed,
                                                        },
                                                    )}
                                                >
                                                    {formatDateTime(
                                                        presentation.preparation_deadline_at,
                                                        timezone,
                                                    )}
                                                </dd>
                                            </div>
                                            <div className="flex justify-between gap-2">
                                                <dt className="text-muted-foreground">
                                                    Presenter
                                                </dt>
                                                <dd className="text-right">
                                                    {presentation.presenter
                                                        ?.name ??
                                                        presentation.presenter_name ??
                                                        presentation.owner
                                                            ?.name}
                                                </dd>
                                            </div>
                                        </dl>
                                        <p className="mt-auto flex items-center gap-1 text-xs text-muted-foreground">
                                            <Paperclip
                                                className="size-3"
                                                aria-hidden
                                            />
                                            {presentation.attachments_count ??
                                                0}{' '}
                                            file(s)
                                            {presentation.venue &&
                                                ` · ${presentation.venue}`}
                                        </p>
                                    </Link>
                                );
                            })}
                        </div>
                        <PaginationNav paginator={presentations} />
                    </>
                )}
            </div>
        </>
    );
}

PresentationsIndex.layout = {
    breadcrumbs: [{ title: 'Presentations', href: index() }],
};
