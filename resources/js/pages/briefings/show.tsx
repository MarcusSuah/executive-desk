import { Head, Link, router } from '@inertiajs/react';
import { Download, ExternalLink, RefreshCw, Send } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuth } from '@/hooks/use-auth';
import { formatDateKey, formatDateTime, formatTime } from '@/lib/datetime';
import { humanize } from '@/lib/labels';
import { generate, index, pdf, send } from '@/routes/briefings';
import type { Briefing, BriefingItem } from '@/types';

export default function BriefingShow({
    briefing: wrapped,
}: {
    briefing: { data: Briefing };
}) {
    const briefing = wrapped.data;
    const { user, can } = useAuth();
    const timezone = briefing.timezone;
    const canManage = briefing.user?.id === user.id || can('briefings.manage');
    const period =
        briefing.period_start === briefing.period_end
            ? formatDateKey(briefing.period_start, {
                  weekday: 'long',
                  month: 'long',
                  day: 'numeric',
                  year: 'numeric',
              })
            : `${formatDateKey(briefing.period_start, { month: 'short', day: 'numeric' })} – ${formatDateKey(briefing.period_end, { month: 'short', day: 'numeric', year: 'numeric' })}`;

    return (
        <>
            <Head title={`${humanize(briefing.type)} briefing`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${humanize(briefing.type)} briefing`}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {period}
                            {briefing.user && (
                                <span>· {briefing.user.name}</span>
                            )}
                            <span>· times in {timezone}</span>
                            <StatusBadge status={briefing.status} />
                        </span>
                    }
                    actions={
                        <>
                            {canManage && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(generate.url(), {
                                            type: briefing.type,
                                            user_id: briefing.user?.id,
                                            date: briefing.period_start,
                                        })
                                    }
                                >
                                    <RefreshCw />
                                    Refresh
                                </Button>
                            )}
                            {canManage && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            send.url(briefing.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Send />
                                    Send now
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <a href={pdf.url(briefing.id)}>
                                    <Download />
                                    PDF
                                </a>
                            </Button>
                        </>
                    }
                />

                <Card>
                    <CardContent className="space-y-3">
                        <p className="text-lg">{briefing.content.headline}</p>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(briefing.content.summary ?? {}).map(
                                ([key, value]) => (
                                    <span
                                        key={key}
                                        className="rounded-lg border px-3 py-2 text-sm"
                                    >
                                        <strong className="mr-1 text-lg tabular-nums">
                                            {value}
                                        </strong>
                                        {humanize(key)}
                                    </span>
                                ),
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Generated{' '}
                            {formatDateTime(briefing.generated_at, timezone)}
                            {briefing.delivered_at &&
                                ` · delivered ${formatDateTime(briefing.delivered_at, timezone)}`}
                        </p>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    {(briefing.content.sections ?? []).map((section) => (
                        <Card
                            key={section.key}
                            className={
                                section.key === 'appointments' ||
                                section.key === 'meetings'
                                    ? 'lg:col-span-2'
                                    : undefined
                            }
                        >
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between text-base">
                                    {section.title}
                                    <span className="text-sm font-normal text-muted-foreground tabular-nums">
                                        {section.items.length}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {section.items.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {section.empty}
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {section.items.map((item) => (
                                            <BriefingRow
                                                key={`${section.key}-${item.id}`}
                                                item={item}
                                                timezone={timezone}
                                            />
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

function BriefingRow({
    item,
    timezone,
}: {
    item: BriefingItem;
    timezone: string;
}) {
    const when = item.starts_at
        ? `${formatDateTime(item.starts_at, timezone)}${item.ends_at ? ` – ${formatTime(item.ends_at, timezone)}` : ''}`
        : item.due_at
          ? `Due ${formatDateTime(item.due_at, timezone)}`
          : item.deadline
            ? `Deadline ${formatDateTime(item.deadline, timezone)}`
            : null;

    const title = item.url ? (
        <Link href={item.url} className="font-medium hover:underline">
            {item.title}
        </Link>
    ) : (
        <span className="font-medium">{item.title}</span>
    );

    return (
        <li className="space-y-1 py-2 text-sm">
            <div className="flex items-start justify-between gap-2">
                {title}
                <span className="flex shrink-0 gap-1">
                    {item.priority &&
                        ['high', 'urgent'].includes(item.priority) && (
                            <PriorityBadge priority={item.priority} />
                        )}
                    {item.status && <StatusBadge status={item.status} />}
                </span>
            </div>
            {when && <p className="text-muted-foreground">{when}</p>}
            {item.location && (
                <p className="text-muted-foreground">{item.location}</p>
            )}
            {item.meeting_url && (
                <a
                    href={item.meeting_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 text-sky-700 hover:underline dark:text-sky-300"
                >
                    <ExternalLink className="size-3.5" aria-hidden />
                    Join meeting
                </a>
            )}
            {item.agenda && (
                <p className="whitespace-pre-line text-muted-foreground">
                    {item.agenda}
                </p>
            )}
            {item.detail && (
                <p className="text-muted-foreground">{item.detail}</p>
            )}
        </li>
    );
}

BriefingShow.layout = {
    breadcrumbs: [{ title: 'Briefings', href: index() }],
};
