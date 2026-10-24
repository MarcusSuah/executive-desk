import { Head, Link, router } from '@inertiajs/react';
import {
    AlarmClock,
    AlertTriangle,
    BellRing,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    CheckCircle2,
    ClipboardList,
    ExternalLink,
    FileText,
    ListTodo,
    MapPin,
    Plus,
    Presentation as PresentationIcon,
    Send,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import EmptyState from '@/components/empty-state';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import {
    countdown,
    formatDate,
    formatDateKey,
    formatDateTime,
    formatTime,
    zoneAbbreviation,
} from '@/lib/datetime';
import { auditLabel, eventTypeClasses, humanize } from '@/lib/labels';
import { cn } from '@/lib/utils';
import { calendar, dashboard } from '@/routes';
import { create as createEvent, show as showEvent } from '@/routes/events';
import { show as showPresentation } from '@/routes/presentations';
import {
    create as createTask,
    index as tasksIndex,
    show as showTask,
} from '@/routes/tasks';
import type {
    AuditEntry,
    Briefing,
    CalendarEvent,
    Collection,
    Presentation,
    Task,
    UserOption,
} from '@/types';

type DashboardProps = {
    now: string;
    today: string;
    todaysEvents: Collection<CalendarEvent>;
    nextEvent: { data: CalendarEvent } | null;
    upcomingEvents: Collection<CalendarEvent>;
    meetingLoad: { date: string; meetings: number; hours: number }[];
    taskCounts: {
        pending: number;
        in_progress: number;
        overdue: number;
        high_priority: number;
        completed_this_week: number;
    };
    dailyCompletion: {
        due_today: number;
        completed_today: number;
        completed_any_today: number;
    };
    overdueTasks: Collection<Task>;
    priorityTasks: Collection<Task>;
    inProgressTasks: Collection<Task>;
    presentationDeadlines: Collection<Presentation>;
    recentChanges: Collection<AuditEntry>;
    upcomingReminders: {
        id: number;
        title: string;
        kind: string;
        channel: string;
        minutes_before: string;
        scheduled_for: string;
    }[];
    deliveryStatus: {
        scope: 'all' | 'mine';
        counts: Record<string, number>;
        pending: number;
    };
    briefings: {
        daily: { data: Briefing } | null;
        weekly: { data: Briefing } | null;
    };
    executive: string;
    executives: UserOption[];
    can: { createEvent: boolean; createTask: boolean };
};

function greeting(hour: number): string {
    if (hour < 12) {
        return 'Good morning';
    }

    return hour < 18 ? 'Good afternoon' : 'Good evening';
}

/**
 * Re-render on an interval so the clock and countdown stay current.
 */
function useNow(intervalMs: number): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), intervalMs);

        return () => window.clearInterval(timer);
    }, [intervalMs]);

    return now;
}

export default function Dashboard(props: DashboardProps) {
    const { user, timezone } = useAuth();
    const now = useNow(30_000);
    const nowIso = new Date(now).toISOString();
    const localHour = Number(
        new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            hour: 'numeric',
            hourCycle: 'h23',
        }).format(now),
    );
    const nextEvent = props.nextEvent?.data ?? null;
    const maxLoad = Math.max(1, ...props.meetingLoad.map((day) => day.hours));
    const taskTotal =
        props.taskCounts.pending +
        props.taskCounts.in_progress +
        props.taskCounts.completed_this_week;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {greeting(localHour)}, {user.name.split(' ')[0]}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {formatDate(nowIso, timezone, {
                                weekday: 'long',
                                month: 'long',
                                day: 'numeric',
                                year: 'numeric',
                            })}{' '}
                            ·{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {formatTime(nowIso, timezone)}
                            </span>{' '}
                            {zoneAbbreviation(nowIso, timezone)} ({timezone})
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {props.executives.length > 0 && (
                            <NativeSelect
                                aria-label="Show data for executive"
                                className="w-auto min-w-48"
                                value={props.executive}
                                onChange={(event) =>
                                    router.get(
                                        dashboard.url({
                                            query: event.target.value
                                                ? {
                                                      executive:
                                                          event.target.value,
                                                  }
                                                : {},
                                        }),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <option value="">All executives</option>
                                {props.executives.map((executive) => (
                                    <option
                                        key={executive.id}
                                        value={executive.id}
                                    >
                                        {executive.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                        {props.can.createEvent && (
                            <Button asChild>
                                <Link href={createEvent()}>
                                    <CalendarPlus />
                                    New appointment
                                </Link>
                            </Button>
                        )}
                        {props.can.createTask && (
                            <Button variant="outline" asChild>
                                <Link href={createTask()}>
                                    <Plus />
                                    New task
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <StatCard
                        label="Today's meetings"
                        value={props.todaysEvents.data.length}
                        icon={CalendarDays}
                        tone="info"
                        href={calendar({ query: { view: 'day' } })}
                    />
                    <StatCard
                        label="Pending tasks"
                        value={props.taskCounts.pending}
                        icon={ListTodo}
                        href={tasksIndex({ query: { tab: 'pending' } })}
                    />
                    <StatCard
                        label="In progress"
                        value={props.taskCounts.in_progress}
                        icon={ClipboardList}
                        tone="info"
                        href={tasksIndex({ query: { tab: 'in_progress' } })}
                    />
                    <StatCard
                        label="Overdue tasks"
                        value={props.taskCounts.overdue}
                        icon={AlertTriangle}
                        tone={
                            props.taskCounts.overdue > 0 ? 'danger' : 'neutral'
                        }
                        href={tasksIndex({ query: { tab: 'overdue' } })}
                    />
                    <StatCard
                        label="High priority open"
                        value={props.taskCounts.high_priority}
                        icon={AlarmClock}
                        tone={
                            props.taskCounts.high_priority > 0
                                ? 'warning'
                                : 'neutral'
                        }
                        href={tasksIndex({ query: { sort: 'priority' } })}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CalendarClock
                                        className="size-4"
                                        aria-hidden
                                    />
                                    Next meeting
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {nextEvent ? (
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0 space-y-1">
                                            <Link
                                                href={showEvent(nextEvent.id)}
                                                className="text-lg font-semibold hover:underline"
                                            >
                                                {nextEvent.title}
                                            </Link>
                                            <p className="text-sm text-muted-foreground">
                                                {formatDateTime(
                                                    nextEvent.starts_at,
                                                    timezone,
                                                )}{' '}
                                                –{' '}
                                                {formatTime(
                                                    nextEvent.ends_at,
                                                    timezone,
                                                )}
                                                {nextEvent.owner &&
                                                    props.executives.length >
                                                        0 && (
                                                        <>
                                                            {' '}
                                                            ·{' '}
                                                            {
                                                                nextEvent.owner
                                                                    .name
                                                            }
                                                        </>
                                                    )}
                                            </p>
                                            {nextEvent.location && (
                                                <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                                    <MapPin
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                    {nextEvent.location}
                                                </p>
                                            )}
                                            {nextEvent.agenda && (
                                                <p className="line-clamp-2 text-sm">
                                                    {nextEvent.agenda}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                                            <span
                                                className="rounded-md bg-sky-50 px-2.5 py-1 text-sm font-semibold text-sky-800 tabular-nums dark:bg-sky-950 dark:text-sky-200"
                                                aria-live="polite"
                                            >
                                                {countdown(
                                                    nextEvent.starts_at,
                                                    now,
                                                )}
                                            </span>
                                            {nextEvent.meeting_url && (
                                                <Button size="sm" asChild>
                                                    <a
                                                        href={
                                                            nextEvent.meeting_url
                                                        }
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <ExternalLink />
                                                        Join meeting
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={CalendarDays}
                                        title="No upcoming meetings"
                                        description="Scheduled appointments will appear here with a live countdown."
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <Card className="gap-4">
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="text-base">
                                        Today's schedule
                                    </CardTitle>
                                    <Link
                                        href={calendar({
                                            query: { view: 'day' },
                                        })}
                                        className="text-sm text-muted-foreground hover:text-foreground"
                                    >
                                        View day
                                    </Link>
                                </CardHeader>
                                <CardContent>
                                    {props.todaysEvents.data.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Nothing scheduled today.
                                        </p>
                                    ) : (
                                        <ol className="space-y-2">
                                            {props.todaysEvents.data.map(
                                                (event) => {
                                                    const isPast =
                                                        new Date(
                                                            event.ends_at,
                                                        ).getTime() < now;

                                                    return (
                                                        <li key={event.id}>
                                                            <Link
                                                                href={showEvent(
                                                                    event.id,
                                                                )}
                                                                className={cn(
                                                                    'flex gap-3 rounded-md border-l-4 px-3 py-2 hover:opacity-90',
                                                                    eventTypeClasses[
                                                                        event
                                                                            .type
                                                                    ],
                                                                    {
                                                                        'opacity-60':
                                                                            isPast,
                                                                    },
                                                                )}
                                                            >
                                                                <span className="w-16 shrink-0 text-xs font-medium tabular-nums">
                                                                    {event.all_day
                                                                        ? 'All day'
                                                                        : formatTime(
                                                                              event.starts_at,
                                                                              timezone,
                                                                          )}
                                                                </span>
                                                                <span className="min-w-0">
                                                                    <span className="block truncate text-sm font-medium">
                                                                        {
                                                                            event.title
                                                                        }
                                                                    </span>
                                                                    <span className="block truncate text-xs text-muted-foreground">
                                                                        {[
                                                                            event.type_label,
                                                                            event.location,
                                                                        ]
                                                                            .filter(
                                                                                Boolean,
                                                                            )
                                                                            .join(
                                                                                ' · ',
                                                                            )}
                                                                    </span>
                                                                </span>
                                                            </Link>
                                                        </li>
                                                    );
                                                },
                                            )}
                                        </ol>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="gap-4">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Next 7 days
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div
                                        className="flex h-28 items-end gap-2"
                                        role="img"
                                        aria-label={`Meeting hours per day: ${props.meetingLoad
                                            .map(
                                                (day) =>
                                                    `${formatDateKey(day.date)} ${day.hours} hours`,
                                            )
                                            .join(', ')}`}
                                    >
                                        {props.meetingLoad.map((day) => (
                                            <div
                                                key={day.date}
                                                className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                            >
                                                <span className="text-[10px] text-muted-foreground tabular-nums">
                                                    {day.hours > 0
                                                        ? `${day.hours}h`
                                                        : ''}
                                                </span>
                                                <div
                                                    className={cn(
                                                        'w-full rounded-t-sm',
                                                        {
                                                            'bg-sky-500':
                                                                day.hours <= 4,
                                                            'bg-amber-500':
                                                                day.hours > 4 &&
                                                                day.hours <= 6,
                                                            'bg-red-500':
                                                                day.hours > 6,
                                                            'bg-muted':
                                                                day.hours === 0,
                                                        },
                                                    )}
                                                    style={{
                                                        height: `${Math.max(4, (day.hours / maxLoad) * 80)}%`,
                                                    }}
                                                />
                                                <span className="text-[10px] text-muted-foreground">
                                                    {formatDateKey(day.date, {
                                                        weekday: 'short',
                                                    })}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    {props.upcomingEvents.data.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No commitments in the coming week.
                                        </p>
                                    ) : (
                                        <ul className="space-y-1.5">
                                            {props.upcomingEvents.data
                                                .slice(0, 6)
                                                .map((event) => (
                                                    <li
                                                        key={event.id}
                                                        className="flex justify-between gap-2 text-sm"
                                                    >
                                                        <Link
                                                            href={showEvent(
                                                                event.id,
                                                            )}
                                                            className="truncate hover:underline"
                                                        >
                                                            {event.title}
                                                        </Link>
                                                        <span className="shrink-0 text-muted-foreground tabular-nums">
                                                            {formatDate(
                                                                event.starts_at,
                                                                timezone,
                                                            )}{' '}
                                                            {!event.all_day &&
                                                                formatTime(
                                                                    event.starts_at,
                                                                    timezone,
                                                                )}
                                                        </span>
                                                    </li>
                                                ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <TaskListCard
                                title="Overdue tasks"
                                icon={AlertTriangle}
                                tasks={props.overdueTasks.data}
                                empty="No overdue tasks."
                                timezone={timezone}
                            />
                            <TaskListCard
                                title="High priority"
                                icon={AlarmClock}
                                tasks={props.priorityTasks.data}
                                empty="No open high-priority tasks."
                                timezone={timezone}
                            />
                        </div>

                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Recent schedule changes
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {props.recentChanges.data.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No changes in the last 7 days.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {props.recentChanges.data.map(
                                            (change) => (
                                                <li
                                                    key={change.id}
                                                    className="flex flex-col gap-0.5 py-2 sm:flex-row sm:items-center sm:justify-between"
                                                >
                                                    <span className="text-sm">
                                                        <span className="font-medium">
                                                            {auditLabel(
                                                                change.action,
                                                            )}
                                                        </span>
                                                        {change.subject &&
                                                            change.auditable_id && (
                                                                <>
                                                                    {': '}
                                                                    <Link
                                                                        href={showEvent(
                                                                            change.auditable_id,
                                                                        )}
                                                                        className="hover:underline"
                                                                    >
                                                                        {
                                                                            change.subject
                                                                        }
                                                                    </Link>
                                                                </>
                                                            )}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {change.actor ??
                                                            'System'}{' '}
                                                        ·{' '}
                                                        {formatDateTime(
                                                            change.created_at,
                                                            timezone,
                                                        )}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CheckCircle2
                                        className="size-4"
                                        aria-hidden
                                    />
                                    Today's progress
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-baseline justify-between">
                                    <span className="text-3xl font-semibold tabular-nums">
                                        {props.dailyCompletion.completed_today}/
                                        {props.dailyCompletion.due_today}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        due today completed
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-emerald-500"
                                        style={{
                                            width: `${
                                                props.dailyCompletion
                                                    .due_today > 0
                                                    ? (props.dailyCompletion
                                                          .completed_today /
                                                          props.dailyCompletion
                                                              .due_today) *
                                                      100
                                                    : 0
                                            }%`,
                                        }}
                                    />
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {props.dailyCompletion.completed_any_today}{' '}
                                    task(s) completed today ·{' '}
                                    {props.taskCounts.completed_this_week} this
                                    week
                                </p>
                                {taskTotal > 0 && (
                                    <div className="space-y-1.5 pt-2">
                                        {(
                                            [
                                                [
                                                    'Pending',
                                                    props.taskCounts.pending,
                                                    'bg-slate-400',
                                                ],
                                                [
                                                    'In progress',
                                                    props.taskCounts
                                                        .in_progress,
                                                    'bg-indigo-500',
                                                ],
                                                [
                                                    'Done this week',
                                                    props.taskCounts
                                                        .completed_this_week,
                                                    'bg-emerald-500',
                                                ],
                                            ] as const
                                        ).map(([label, value, color]) => (
                                            <div
                                                key={label}
                                                className="flex items-center gap-2 text-xs"
                                            >
                                                <span className="w-24 text-muted-foreground">
                                                    {label}
                                                </span>
                                                <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={cn(
                                                            'h-full',
                                                            color,
                                                        )}
                                                        style={{
                                                            width: `${(value / taskTotal) * 100}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="w-6 text-right tabular-nums">
                                                    {value}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <PresentationIcon
                                        className="size-4"
                                        aria-hidden
                                    />
                                    Presentation deadlines
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {props.presentationDeadlines.data.length ===
                                0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No presentations in preparation.
                                    </p>
                                ) : (
                                    <ul className="space-y-3">
                                        {props.presentationDeadlines.data.map(
                                            (presentation) => (
                                                <li
                                                    key={presentation.id}
                                                    className="space-y-0.5"
                                                >
                                                    <Link
                                                        href={showPresentation(
                                                            presentation.id,
                                                        )}
                                                        className="text-sm font-medium hover:underline"
                                                    >
                                                        {presentation.title}
                                                    </Link>
                                                    <p className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                        <StatusBadge
                                                            status={
                                                                presentation.status
                                                            }
                                                        />
                                                        {presentation.preparation_deadline_at && (
                                                            <span>
                                                                Prep due{' '}
                                                                {formatDateTime(
                                                                    presentation.preparation_deadline_at,
                                                                    timezone,
                                                                )}
                                                            </span>
                                                        )}
                                                    </p>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <BellRing className="size-4" aria-hidden />
                                    Your next reminders
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {props.upcomingReminders.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No reminders in the next 24 hours.
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {props.upcomingReminders.map(
                                            (reminder) => (
                                                <li
                                                    key={reminder.id}
                                                    className="flex items-start justify-between gap-2 text-sm"
                                                >
                                                    <span className="min-w-0">
                                                        <span className="block truncate">
                                                            {reminder.title}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {reminder.channel} ·{' '}
                                                            {
                                                                reminder.minutes_before
                                                            }{' '}
                                                            before
                                                        </span>
                                                    </span>
                                                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                        {formatTime(
                                                            reminder.scheduled_for,
                                                            timezone,
                                                        )}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Send className="size-4" aria-hidden />
                                    Notification delivery
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <p className="text-xs text-muted-foreground">
                                    Last 7 days ·{' '}
                                    {props.deliveryStatus.scope === 'all'
                                        ? 'all users'
                                        : 'your notifications'}
                                </p>
                                <div className="grid grid-cols-2 gap-2">
                                    {(
                                        [
                                            'sent',
                                            'failed',
                                            'unavailable',
                                            'skipped',
                                        ] as const
                                    ).map((status) => (
                                        <div
                                            key={status}
                                            className="flex items-center justify-between rounded-md border px-2.5 py-1.5"
                                        >
                                            <StatusBadge status={status} />
                                            <span className="text-sm font-semibold tabular-nums">
                                                {props.deliveryStatus.counts[
                                                    status
                                                ] ?? 0}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {props.deliveryStatus.pending} reminder(s)
                                    scheduled in the next 24 hours. “Sent” means
                                    the provider accepted the message.
                                </p>
                            </CardContent>
                        </Card>

                        <BriefingPreview
                            label="Daily briefing"
                            briefing={props.briefings.daily?.data ?? null}
                        />
                        <BriefingPreview
                            label="Weekly briefing"
                            briefing={props.briefings.weekly?.data ?? null}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

function TaskListCard({
    title,
    icon: Icon,
    tasks,
    empty,
    timezone,
}: {
    title: string;
    icon: typeof AlertTriangle;
    tasks: Task[];
    empty: string;
    timezone: string;
}) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Icon className="size-4" aria-hidden />
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {tasks.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    <ul className="space-y-2">
                        {tasks.map((task) => (
                            <li
                                key={task.id}
                                className="flex items-start justify-between gap-2"
                            >
                                <span className="min-w-0">
                                    <Link
                                        href={showTask(task.id)}
                                        className="block truncate text-sm font-medium hover:underline"
                                    >
                                        {task.title}
                                    </Link>
                                    <span
                                        className={cn(
                                            'text-xs',
                                            task.is_overdue
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {task.due_at
                                            ? `Due ${formatDateTime(task.due_at, timezone)}`
                                            : 'No due date'}
                                        {task.assignee &&
                                            ` · ${task.assignee.name}`}
                                    </span>
                                </span>
                                <PriorityBadge priority={task.priority} />
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function BriefingPreview({
    label,
    briefing,
}: {
    label: string;
    briefing: Briefing | null;
}) {
    return (
        <Card className="gap-3">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <FileText className="size-4" aria-hidden />
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {briefing ? (
                    <div className="space-y-2">
                        <p className="text-sm">
                            {briefing.content.headline ??
                                humanize(briefing.type)}
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {Object.entries(briefing.content.summary ?? {}).map(
                                ([key, value]) => (
                                    <span
                                        key={key}
                                        className="rounded-md bg-muted px-2 py-0.5 text-xs"
                                    >
                                        {humanize(key)}:{' '}
                                        <strong className="tabular-nums">
                                            {value}
                                        </strong>
                                    </span>
                                ),
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {formatDateKey(briefing.period_start)}
                            {briefing.period_end !== briefing.period_start &&
                                ` – ${formatDateKey(briefing.period_end)}`}
                        </p>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No briefing generated yet.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
