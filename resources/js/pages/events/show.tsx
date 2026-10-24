import { Head, Link, router } from '@inertiajs/react';
import {
    BellOff,
    BellRing,
    CalendarCheck,
    ExternalLink,
    MapPin,
    Pencil,
    Plus,
    Trash2,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import AttachmentsPanel from '@/components/attachments-panel';
import ChecklistPanel from '@/components/checklist-panel';
import ConfirmDialog from '@/components/confirm-dialog';
import HistoryList from '@/components/history-list';
import PageHeader from '@/components/page-header';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/hooks/use-auth';
import {
    describeMinutes,
    formatDateTime,
    formatTimeRange,
    zoneAbbreviation,
} from '@/lib/datetime';
import { humanize } from '@/lib/labels';
import { calendar } from '@/routes';
import { store as storeAttachment } from '@/routes/events/attachments';
import { store as storeChecklist } from '@/routes/events/checklist';
import { cancel, complete, destroy, edit } from '@/routes/events';
import { show as showPresentation } from '@/routes/presentations';
import { toggle as toggleReminder } from '@/routes/reminders';
import { create as createTask, show as showTask } from '@/routes/tasks';
import type { AuditEntry, CalendarEvent, Collection, Reminder } from '@/types';

type EventShowProps = {
    event: { data: CalendarEvent };
    reminders: Collection<Reminder>;
    history: Collection<AuditEntry>;
    can: {
        update: boolean;
        cancel: boolean;
        delete: boolean;
        updateChecklist: boolean;
        attachFiles: boolean;
        complete: boolean;
    };
};

export default function EventShow({
    event: wrapped,
    reminders,
    history,
    can,
}: EventShowProps) {
    const event = wrapped.data;
    const { timezone } = useAuth();
    const [reason, setReason] = useState('');
    const differentZone = event.timezone !== timezone;

    return (
        <>
            <Head title={event.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={event.title}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={event.status} />
                            <PriorityBadge priority={event.priority} />
                            <span>{event.type_label}</span>
                            {event.category && <span>· {event.category}</span>}
                        </span>
                    }
                    actions={
                        <>
                            {can.complete && event.status === 'scheduled' && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(complete.url(event.id))
                                    }
                                >
                                    <CalendarCheck />
                                    Mark completed
                                </Button>
                            )}
                            {can.update && (
                                <Button variant="outline" asChild>
                                    <Link href={edit(event.id)}>
                                        <Pencil />
                                        Edit / reschedule
                                    </Link>
                                </Button>
                            )}
                            {can.cancel && (
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="outline">
                                            <XCircle />
                                            Cancel appointment
                                        </Button>
                                    }
                                    title="Cancel this appointment?"
                                    description="Pending reminders are cancelled and attendees who opted in are notified."
                                    confirmLabel="Cancel appointment"
                                    destructive
                                    onConfirm={(close, setBusy) => {
                                        setBusy(true);
                                        router.post(
                                            cancel.url(event.id),
                                            { reason },
                                            {
                                                onFinish: () => {
                                                    setBusy(false);
                                                    close();
                                                },
                                            },
                                        );
                                    }}
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="cancel-reason">
                                            Reason (optional)
                                        </Label>
                                        <Textarea
                                            id="cancel-reason"
                                            value={reason}
                                            onChange={(e) =>
                                                setReason(e.target.value)
                                            }
                                            maxLength={500}
                                            rows={3}
                                        />
                                    </div>
                                </ConfirmDialog>
                            )}
                            {can.delete && (
                                <ConfirmDialog
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Delete appointment"
                                        >
                                            <Trash2 />
                                        </Button>
                                    }
                                    title="Delete this appointment?"
                                    description="The appointment is removed from the calendar. Its history is kept in the audit log."
                                    confirmLabel="Delete"
                                    destructive
                                    onConfirm={(close, setBusy) => {
                                        setBusy(true);
                                        router.delete(destroy.url(event.id), {
                                            onFinish: () => {
                                                setBusy(false);
                                                close();
                                            },
                                        });
                                    }}
                                />
                            )}
                        </>
                    }
                />

                {event.status === 'cancelled' && (
                    <div className="rounded-lg border bg-muted/50 p-4 text-sm">
                        Cancelled {formatDateTime(event.cancelled_at, timezone)}
                        {event.cancellation_reason && (
                            <> — {event.cancellation_reason}</>
                        )}
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <Card>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        When
                                    </p>
                                    <p className="font-medium">
                                        {event.all_day
                                            ? formatDateTime(
                                                  event.starts_at,
                                                  timezone,
                                                  {
                                                      hour: undefined,
                                                      minute: undefined,
                                                  },
                                              ) + ' (all day)'
                                            : formatTimeRange(
                                                  event.starts_at,
                                                  event.ends_at,
                                                  timezone,
                                              )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {formatDateTime(
                                            event.starts_at,
                                            timezone,
                                            {
                                                hour: undefined,
                                                minute: undefined,
                                                weekday: 'long',
                                            },
                                        )}{' '}
                                        ·{' '}
                                        {zoneAbbreviation(
                                            event.starts_at,
                                            timezone,
                                        )}
                                    </p>
                                    {differentZone && !event.all_day && (
                                        <p className="text-sm text-muted-foreground">
                                            In {event.timezone}:{' '}
                                            {formatTimeRange(
                                                event.starts_at,
                                                event.ends_at,
                                                event.timezone,
                                            )}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    {event.location && (
                                        <p className="flex items-center gap-2 text-sm">
                                            <MapPin
                                                className="size-4 text-muted-foreground"
                                                aria-hidden
                                            />
                                            {event.location}
                                        </p>
                                    )}
                                    {event.meeting_url && (
                                        <Button size="sm" asChild>
                                            <a
                                                href={event.meeting_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <ExternalLink />
                                                Join meeting
                                            </a>
                                        </Button>
                                    )}
                                    {event.owner && (
                                        <p className="text-sm text-muted-foreground">
                                            Calendar:{' '}
                                            <span className="text-foreground">
                                                {event.owner.name}
                                            </span>
                                        </p>
                                    )}
                                    {event.creator && (
                                        <p className="text-sm text-muted-foreground">
                                            Scheduled by {event.creator.name}
                                        </p>
                                    )}
                                    {event.conflict_acknowledged && (
                                        <p className="text-sm text-amber-700 dark:text-amber-300">
                                            Saved despite overlapping another
                                            appointment.
                                        </p>
                                    )}
                                </div>
                                {event.description && (
                                    <p className="text-sm whitespace-pre-line sm:col-span-2">
                                        {event.description}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {(event.agenda ||
                            event.preparation_notes ||
                            event.notes) && (
                            <Card>
                                <CardContent className="space-y-4">
                                    {(
                                        [
                                            ['Agenda', event.agenda],
                                            [
                                                'Preparation notes',
                                                event.preparation_notes,
                                            ],
                                            ['Meeting notes', event.notes],
                                        ] as const
                                    )
                                        .filter(([, value]) => value)
                                        .map(([label, value]) => (
                                            <section key={label}>
                                                <h2 className="mb-1 text-sm font-semibold">
                                                    {label}
                                                </h2>
                                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                                    {value}
                                                </p>
                                            </section>
                                        ))}
                                </CardContent>
                            </Card>
                        )}

                        <div className="grid gap-6 lg:grid-cols-2">
                            <ChecklistPanel
                                title="Preparation checklist"
                                items={event.checklist ?? []}
                                storeUrl={storeChecklist.url(event.id)}
                                canEdit={can.updateChecklist}
                            />
                            <AttachmentsPanel
                                title="Supporting documents"
                                attachments={event.attachments ?? []}
                                uploadUrl={storeAttachment.url(event.id)}
                                canUpload={can.attachFiles}
                            />
                        </div>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-base">
                                    Related tasks and presentations
                                </CardTitle>
                                {can.update && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={createTask({
                                                query: { event: event.id },
                                            })}
                                        >
                                            <Plus />
                                            Task
                                        </Link>
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {(event.tasks ?? []).length === 0 &&
                                (event.presentations ?? []).length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nothing linked to this appointment.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {(event.tasks ?? []).map((task) => (
                                            <li
                                                key={`task-${task.id}`}
                                                className="flex items-center justify-between gap-2 py-2"
                                            >
                                                <Link
                                                    href={showTask(task.id)}
                                                    className="text-sm hover:underline"
                                                >
                                                    {task.title}
                                                </Link>
                                                <StatusBadge
                                                    status={task.status}
                                                />
                                            </li>
                                        ))}
                                        {(event.presentations ?? []).map(
                                            (presentation) => (
                                                <li
                                                    key={`presentation-${presentation.id}`}
                                                    className="flex items-center justify-between gap-2 py-2"
                                                >
                                                    <Link
                                                        href={showPresentation(
                                                            presentation.id,
                                                        )}
                                                        className="text-sm hover:underline"
                                                    >
                                                        {presentation.title}
                                                    </Link>
                                                    <StatusBadge
                                                        status={
                                                            presentation.status
                                                        }
                                                    />
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Users className="size-4" aria-hidden />
                                    Attendees
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2">
                                    {event.owner && (
                                        <li className="flex items-center justify-between text-sm">
                                            <span>{event.owner.name}</span>
                                            <span className="text-xs text-muted-foreground">
                                                Executive
                                            </span>
                                        </li>
                                    )}
                                    {(event.attendees ?? []).map((attendee) => (
                                        <li
                                            key={attendee.id}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate">
                                                    {attendee.name}
                                                </span>
                                                {attendee.email && (
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {attendee.email}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="shrink-0 text-right text-xs text-muted-foreground">
                                                {humanize(attendee.role)}
                                                <br />
                                                {humanize(
                                                    attendee.response_status,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <BellRing className="size-4" aria-hidden />
                                    Reminders
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {reminders.data.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No reminders scheduled.
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {reminders.data.map((reminder) => (
                                            <li
                                                key={reminder.id}
                                                className="flex items-center justify-between gap-2 text-sm"
                                            >
                                                <span className="min-w-0">
                                                    <span className="block">
                                                        {describeMinutes(
                                                            reminder.minutes_before,
                                                        )}{' '}
                                                        before ·{' '}
                                                        {reminder.channel_label}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {reminder.recipient &&
                                                            `${reminder.recipient} · `}
                                                        {formatDateTime(
                                                            reminder.scheduled_for,
                                                            timezone,
                                                        )}
                                                    </span>
                                                </span>
                                                <span className="flex shrink-0 items-center gap-1">
                                                    <StatusBadge
                                                        status={reminder.status}
                                                    />
                                                    {reminder.can_toggle && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7"
                                                            onClick={() =>
                                                                router.patch(
                                                                    toggleReminder.url(
                                                                        reminder.id,
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                            aria-label={
                                                                reminder.status ===
                                                                'disabled'
                                                                    ? 'Turn reminder on'
                                                                    : 'Turn reminder off'
                                                            }
                                                        >
                                                            {reminder.status ===
                                                            'disabled' ? (
                                                                <BellRing className="size-3.5" />
                                                            ) : (
                                                                <BellOff className="size-3.5" />
                                                            )}
                                                        </Button>
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <HistoryList entries={history.data} />
                    </div>
                </div>
            </div>
        </>
    );
}

EventShow.layout = {
    breadcrumbs: [{ title: 'Calendar', href: calendar() }],
};
