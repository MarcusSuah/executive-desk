import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, Pencil, Repeat, Trash2 } from 'lucide-react';
import AttachmentsPanel from '@/components/attachments-panel';
import ChecklistPanel from '@/components/checklist-panel';
import ConfirmDialog from '@/components/confirm-dialog';
import HistoryList from '@/components/history-list';
import PageHeader from '@/components/page-header';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { useOfflineQueue } from '@/hooks/use-offline-queue';
import { describeMinutes, formatDateTime } from '@/lib/datetime';
import { humanize } from '@/lib/labels';
import { show as showEvent } from '@/routes/events';
import { destroy, edit, index, status as updateStatus } from '@/routes/tasks';
import { store as storeAttachment } from '@/routes/tasks/attachments';
import { store as storeChecklist } from '@/routes/tasks/checklist';
import type { AuditEntry, Collection, Option, Task } from '@/types';

type TaskShowProps = {
    task: { data: Task };
    history: Collection<AuditEntry>;
    statuses: Option[];
    can: { update: boolean; updateProgress: boolean; delete: boolean };
};

export default function TaskShow({
    task: wrapped,
    history,
    statuses,
    can,
}: TaskShowProps) {
    const task = wrapped.data;
    const { timezone } = useAuth();
    const { changeTaskStatus } = useOfflineQueue();

    const details: [string, string | null][] = [
        ['Assigned to', task.assignee?.name ?? 'Unassigned'],
        ['Created by', task.creator?.name ?? null],
        [
            'Start',
            task.starts_at ? formatDateTime(task.starts_at, timezone) : null,
        ],
        ['Due', task.due_at ? formatDateTime(task.due_at, timezone) : null],
        [
            'Estimated',
            task.estimated_minutes
                ? describeMinutes(task.estimated_minutes)
                : null,
        ],
        ['Project', task.project],
        [
            'Completed',
            task.completed_at
                ? `${formatDateTime(task.completed_at, timezone)}${task.completer ? ` by ${task.completer.name}` : ''}`
                : null,
        ],
        [
            'Repeats',
            task.recurrence_frequency
                ? `${humanize(task.recurrence_frequency)}${(task.recurrence_interval ?? 1) > 1 ? ` (every ${task.recurrence_interval})` : ''}${task.recurrence_ends_on ? ` until ${task.recurrence_ends_on}` : ''}`
                : null,
        ],
        [
            'Occurrence of',
            task.recurrence_parent_id
                ? `Recurring task #${task.recurrence_parent_id} (${task.occurrence_date})`
                : null,
        ],
    ];

    return (
        <>
            <Head title={task.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={task.title}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={task.status} />
                            <PriorityBadge priority={task.priority} />
                            {task.is_overdue && (
                                <StatusBadge status="overdue" />
                            )}
                            {(task.recurrence_frequency ||
                                task.recurrence_parent_id) && (
                                <span className="flex items-center gap-1">
                                    <Repeat className="size-3.5" aria-hidden />
                                    Recurring
                                </span>
                            )}
                        </span>
                    }
                    actions={
                        <>
                            {can.updateProgress && (
                                <NativeSelect
                                    className="w-auto"
                                    value={task.status}
                                    onChange={(e) =>
                                        changeTaskStatus(
                                            task,
                                            e.target.value as Task['status'],
                                            () =>
                                                router.patch(
                                                    updateStatus.url(task.id),
                                                    { status: e.target.value },
                                                    { preserveScroll: true },
                                                ),
                                        )
                                    }
                                    aria-label="Task status"
                                >
                                    {statuses.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                            {can.update && (
                                <Button variant="outline" asChild>
                                    <Link href={edit(task.id)}>
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
                                            aria-label="Delete task"
                                        >
                                            <Trash2 />
                                        </Button>
                                    }
                                    title="Delete this task?"
                                    description="Pending reminders for this task are cancelled."
                                    confirmLabel="Delete task"
                                    destructive
                                    onConfirm={(close, setBusy) => {
                                        setBusy(true);
                                        router.delete(destroy.url(task.id), {
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

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <Card>
                            <CardContent className="space-y-4">
                                <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                                    {details
                                        .filter(([, value]) => value)
                                        .map(([label, value]) => (
                                            <div key={label}>
                                                <dt className="text-xs font-medium text-muted-foreground uppercase">
                                                    {label}
                                                </dt>
                                                <dd className="text-sm">
                                                    {value}
                                                </dd>
                                            </div>
                                        ))}
                                    {task.event && (
                                        <div>
                                            <dt className="text-xs font-medium text-muted-foreground uppercase">
                                                Related meeting
                                            </dt>
                                            <dd>
                                                <Link
                                                    href={showEvent(
                                                        task.event.id,
                                                    )}
                                                    className="flex items-center gap-1 text-sm hover:underline"
                                                >
                                                    <CalendarDays
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                    {task.event.title} ·{' '}
                                                    {formatDateTime(
                                                        task.event.starts_at,
                                                        timezone,
                                                    )}
                                                </Link>
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                                {task.description && (
                                    <p className="text-sm whitespace-pre-line">
                                        {task.description}
                                    </p>
                                )}
                                {task.notes && (
                                    <section>
                                        <h2 className="mb-1 text-sm font-semibold">
                                            Notes
                                        </h2>
                                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                                            {task.notes}
                                        </p>
                                    </section>
                                )}
                            </CardContent>
                        </Card>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <ChecklistPanel
                                items={task.checklist ?? []}
                                storeUrl={storeChecklist.url(task.id)}
                                canEdit={can.updateProgress}
                            />
                            <AttachmentsPanel
                                attachments={task.attachments ?? []}
                                uploadUrl={storeAttachment.url(task.id)}
                                canUpload={can.updateProgress}
                            />
                        </div>
                    </div>

                    <HistoryList entries={history.data} />
                </div>
            </div>
        </>
    );
}

TaskShow.layout = {
    breadcrumbs: [{ title: 'Tasks', href: index() }],
};
