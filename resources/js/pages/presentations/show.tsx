import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    CalendarDays,
    ExternalLink,
    MapPin,
    Pencil,
    Trash2,
} from 'lucide-react';
import AttachmentsPanel from '@/components/attachments-panel';
import ChecklistPanel from '@/components/checklist-panel';
import ConfirmDialog from '@/components/confirm-dialog';
import HistoryList from '@/components/history-list';
import PageHeader from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAuth } from '@/hooks/use-auth';
import { countdown, formatDateTime } from '@/lib/datetime';
import { show as showEvent } from '@/routes/events';
import { approve, destroy, edit, index } from '@/routes/presentations';
import { store as storeAttachment } from '@/routes/presentations/attachments';
import { store as storeChecklist } from '@/routes/presentations/checklist';
import type { AuditEntry, Collection, Presentation } from '@/types';

type PresentationShowProps = {
    presentation: { data: Presentation };
    history: Collection<AuditEntry>;
    can: {
        update: boolean;
        delete: boolean;
        approve: boolean;
        contribute: boolean;
    };
};

export default function PresentationShow({
    presentation: wrapped,
    history,
    can,
}: PresentationShowProps) {
    const presentation = wrapped.data;
    const { timezone } = useAuth();
    const now = Date.now();

    const deadlines: [string, string | null][] = [
        ['Presentation', presentation.presentation_at],
        ['Preparation deadline', presentation.preparation_deadline_at],
        ['Review deadline', presentation.review_deadline_at],
    ];

    return (
        <>
            <Head title={presentation.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={presentation.title}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={presentation.status} />
                            {presentation.owner && (
                                <span>for {presentation.owner.name}</span>
                            )}
                            {presentation.approved_at &&
                                presentation.approver && (
                                    <span>
                                        · approved by{' '}
                                        {presentation.approver.name}{' '}
                                        {formatDateTime(
                                            presentation.approved_at,
                                            timezone,
                                        )}
                                    </span>
                                )}
                        </span>
                    }
                    actions={
                        <>
                            {can.approve && (
                                <ConfirmDialog
                                    trigger={
                                        <Button>
                                            <BadgeCheck />
                                            Approve
                                        </Button>
                                    }
                                    title="Approve this presentation?"
                                    description="Approval is recorded with your name and time, and deadline reminders stop."
                                    confirmLabel="Approve"
                                    onConfirm={(close, setBusy) => {
                                        setBusy(true);
                                        router.post(
                                            approve.url(presentation.id),
                                            {},
                                            {
                                                onFinish: () => {
                                                    setBusy(false);
                                                    close();
                                                },
                                            },
                                        );
                                    }}
                                />
                            )}
                            {can.update && (
                                <Button variant="outline" asChild>
                                    <Link href={edit(presentation.id)}>
                                        <Pencil />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                            {can.delete && (
                                <ConfirmDialog
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Delete presentation"
                                        >
                                            <Trash2 />
                                        </Button>
                                    }
                                    title="Delete this presentation?"
                                    description="Its reminders are cancelled. Files remain in the audit trail only as names."
                                    confirmLabel="Delete"
                                    destructive
                                    onConfirm={(close, setBusy) => {
                                        setBusy(true);
                                        router.delete(
                                            destroy.url(presentation.id),
                                            {
                                                onFinish: () => {
                                                    setBusy(false);
                                                    close();
                                                },
                                            },
                                        );
                                    }}
                                />
                            )}
                        </>
                    }
                />

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <Card>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    {deadlines.map(([label, value]) => (
                                        <div
                                            key={label}
                                            className="rounded-lg border p-3"
                                        >
                                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                                {label}
                                            </p>
                                            <p className="text-sm font-medium">
                                                {formatDateTime(
                                                    value,
                                                    timezone,
                                                )}
                                            </p>
                                            {value && (
                                                <p
                                                    className={
                                                        new Date(
                                                            value,
                                                        ).getTime() < now
                                                            ? 'text-xs text-muted-foreground'
                                                            : 'text-xs text-sky-700 dark:text-sky-300'
                                                    }
                                                >
                                                    {new Date(value).getTime() <
                                                    now
                                                        ? 'Passed'
                                                        : countdown(value, now)}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                    <span>
                                        Presenter:{' '}
                                        {presentation.presenter?.name ??
                                            presentation.presenter_name ??
                                            presentation.owner?.name}
                                    </span>
                                    {presentation.venue && (
                                        <span className="flex items-center gap-1">
                                            <MapPin
                                                className="size-3.5 text-muted-foreground"
                                                aria-hidden
                                            />
                                            {presentation.venue}
                                        </span>
                                    )}
                                    {presentation.event && (
                                        <Link
                                            href={showEvent(
                                                presentation.event.id,
                                            )}
                                            className="flex items-center gap-1 hover:underline"
                                        >
                                            <CalendarDays
                                                className="size-3.5 text-muted-foreground"
                                                aria-hidden
                                            />
                                            {presentation.event.title}
                                        </Link>
                                    )}
                                    {presentation.meeting_url && (
                                        <a
                                            href={presentation.meeting_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center gap-1 hover:underline"
                                        >
                                            <ExternalLink
                                                className="size-3.5"
                                                aria-hidden
                                            />
                                            Meeting link
                                        </a>
                                    )}
                                </div>
                                {presentation.description && (
                                    <p className="text-sm whitespace-pre-line">
                                        {presentation.description}
                                    </p>
                                )}
                                {presentation.notes && (
                                    <section>
                                        <h2 className="mb-1 text-sm font-semibold">
                                            Notes
                                        </h2>
                                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                                            {presentation.notes}
                                        </p>
                                    </section>
                                )}
                            </CardContent>
                        </Card>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <AttachmentsPanel
                                title="Slides and supporting files"
                                attachments={presentation.attachments ?? []}
                                uploadUrl={storeAttachment.url(presentation.id)}
                                canUpload={can.contribute}
                            />
                            <ChecklistPanel
                                title="Preparation checklist"
                                items={presentation.checklist ?? []}
                                storeUrl={storeChecklist.url(presentation.id)}
                                canEdit={can.contribute}
                            />
                        </div>
                    </div>

                    <HistoryList entries={history.data} />
                </div>
            </div>
        </>
    );
}

PresentationShow.layout = {
    breadcrumbs: [{ title: 'Presentations', href: index() }],
};
