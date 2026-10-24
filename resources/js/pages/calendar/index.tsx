import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    MapPin,
    Search,
    Video,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import {
    addDays,
    dateKey,
    formatDateKey,
    formatTime,
    formatTimeRange,
    minutesIntoDay,
} from '@/lib/datetime';
import { eventTypeClasses } from '@/lib/labels';
import { cn } from '@/lib/utils';
import { calendar } from '@/routes';
import { create as createEvent, show as showEvent } from '@/routes/events';
import type { CalendarEvent, Collection, Option, UserOption } from '@/types';

type View = 'day' | 'week' | 'month' | 'agenda';

type Filters = {
    q: string;
    type: string;
    priority: string;
    status: string;
    executive: string;
};

type CalendarProps = {
    view: View;
    date: string;
    today: string;
    days: string[];
    previousDate: string;
    nextDate: string;
    events: Collection<CalendarEvent>;
    filters: Filters;
    options: {
        types: Option[];
        priorities: Option[];
        statuses: Option[];
        executives: UserOption[];
    };
    canCreate: boolean;
};

const HOUR_HEIGHT = 48;
const VIEWS: { value: View; label: string }[] = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
    { value: 'agenda', label: 'Agenda' },
];

/**
 * Get the local dates ("YYYY-MM-DD") an event covers in the viewer's time zone.
 */
function coveredDays(event: CalendarEvent, timezone: string): string[] {
    const first = dateKey(event.starts_at, timezone);
    // An event ending exactly at midnight does not cover the next day.
    const endIso = new Date(
        new Date(event.ends_at).getTime() - 60_000,
    ).toISOString();
    const last =
        dateKey(endIso, timezone) < first ? first : dateKey(endIso, timezone);
    const days: string[] = [];

    for (
        let day = first;
        day <= last && days.length < 62;
        day = addDays(day, 1)
    ) {
        days.push(day);
    }

    return days;
}

export default function CalendarIndex(props: CalendarProps) {
    const { timezone } = useAuth();
    const [search, setSearch] = useState(props.filters.q);

    const visit = (
        overrides: Partial<Filters & { view: View; date: string }>,
    ) => {
        const query = Object.fromEntries(
            Object.entries({
                ...props.filters,
                view: props.view,
                date: props.date,
                ...overrides,
            }).filter(([, value]) => value !== ''),
        );

        router.get(
            calendar.url({ query }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search });
    };

    const eventsByDay = new Map<string, CalendarEvent[]>();
    for (const event of props.events.data) {
        for (const day of coveredDays(event, timezone)) {
            eventsByDay.set(day, [...(eventsByDay.get(day) ?? []), event]);
        }
    }

    const rangeLabel =
        props.view === 'month'
            ? formatDateKey(props.date, { month: 'long', year: 'numeric' })
            : props.view === 'day'
              ? formatDateKey(props.date, {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                })
              : `${formatDateKey(props.days[0], { month: 'short', day: 'numeric' })} – ${formatDateKey(props.days[props.days.length - 1], { month: 'short', day: 'numeric', year: 'numeric' })}`;

    return (
        <>
            <Head title="Calendar" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Calendar"
                    description={
                        <>
                            {rangeLabel} · times shown in {timezone}
                        </>
                    }
                    actions={
                        props.canCreate && (
                            <Button asChild>
                                <Link
                                    href={createEvent({
                                        query: {
                                            date: props.date,
                                            executive:
                                                props.filters.executive ||
                                                undefined,
                                        },
                                    })}
                                >
                                    <CalendarPlus />
                                    New appointment
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => visit({ date: props.today })}
                        >
                            Today
                        </Button>
                        <div className="flex">
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8 rounded-r-none"
                                onClick={() =>
                                    visit({ date: props.previousDate })
                                }
                                aria-label="Previous period"
                            >
                                <ChevronLeft />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8 rounded-l-none border-l-0"
                                onClick={() => visit({ date: props.nextDate })}
                                aria-label="Next period"
                            >
                                <ChevronRight />
                            </Button>
                        </div>
                        <Input
                            type="date"
                            className="h-8 w-auto"
                            value={props.date}
                            onChange={(event) =>
                                event.target.value &&
                                visit({ date: event.target.value })
                            }
                            aria-label="Jump to date"
                        />
                        <div
                            className="inline-flex rounded-md border p-0.5"
                            role="group"
                            aria-label="Calendar view"
                        >
                            {VIEWS.map((view) => (
                                <button
                                    key={view.value}
                                    type="button"
                                    onClick={() => visit({ view: view.value })}
                                    aria-pressed={props.view === view.value}
                                    className={cn(
                                        'rounded px-3 py-1 text-sm transition-colors',
                                        props.view === view.value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {view.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <form onSubmit={submitSearch} className="relative">
                            <Search
                                className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search title or location"
                                className="h-9 w-56 pl-8"
                                aria-label="Search appointments"
                            />
                        </form>
                        <NativeSelect
                            className="w-auto"
                            value={props.filters.type}
                            onChange={(event) =>
                                visit({ type: event.target.value })
                            }
                            aria-label="Filter by type"
                        >
                            <option value="">All types</option>
                            {props.options.types.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                        <NativeSelect
                            className="w-auto"
                            value={props.filters.priority}
                            onChange={(event) =>
                                visit({ priority: event.target.value })
                            }
                            aria-label="Filter by priority"
                        >
                            <option value="">All priorities</option>
                            {props.options.priorities.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                        <NativeSelect
                            className="w-auto"
                            value={props.filters.status}
                            onChange={(event) =>
                                visit({ status: event.target.value })
                            }
                            aria-label="Filter by status"
                        >
                            <option value="">Active</option>
                            {props.options.statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                        {props.options.executives.length > 0 && (
                            <NativeSelect
                                className="w-auto"
                                value={props.filters.executive}
                                onChange={(event) =>
                                    visit({ executive: event.target.value })
                                }
                                aria-label="Filter by executive"
                            >
                                <option value="">All executives</option>
                                {props.options.executives.map((executive) => (
                                    <option
                                        key={executive.id}
                                        value={executive.id}
                                    >
                                        {executive.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </div>
                </div>

                {props.view === 'month' && (
                    <MonthView
                        {...props}
                        eventsByDay={eventsByDay}
                        timezone={timezone}
                        onOpenDay={(day) => visit({ view: 'day', date: day })}
                    />
                )}
                {(props.view === 'week' || props.view === 'day') && (
                    <TimeGridView
                        {...props}
                        eventsByDay={eventsByDay}
                        timezone={timezone}
                    />
                )}
                {props.view === 'agenda' && (
                    <AgendaView
                        days={props.days}
                        eventsByDay={eventsByDay}
                        timezone={timezone}
                        today={props.today}
                    />
                )}
            </div>
        </>
    );
}

function MonthView({
    days,
    date,
    today,
    eventsByDay,
    timezone,
    onOpenDay,
}: CalendarProps & {
    eventsByDay: Map<string, CalendarEvent[]>;
    timezone: string;
    onOpenDay: (day: string) => void;
}) {
    const currentMonth = date.slice(0, 7);

    return (
        <div className="overflow-hidden rounded-xl border">
            <div className="grid grid-cols-7 border-b bg-muted/40 text-center text-xs font-medium text-muted-foreground">
                {days.slice(0, 7).map((day) => (
                    <div key={day} className="py-2">
                        {formatDateKey(day, { weekday: 'short' })}
                    </div>
                ))}
            </div>
            <div className="grid grid-cols-7">
                {days.map((day) => {
                    const events = eventsByDay.get(day) ?? [];

                    return (
                        <div
                            key={day}
                            className={cn(
                                'min-h-28 border-r border-b p-1.5 [&:nth-child(7n)]:border-r-0',
                                {
                                    'bg-muted/30 text-muted-foreground':
                                        !day.startsWith(currentMonth),
                                },
                            )}
                        >
                            <button
                                type="button"
                                onClick={() => onOpenDay(day)}
                                className={cn(
                                    'mb-1 inline-flex size-6 items-center justify-center rounded-full text-xs hover:bg-accent',
                                    {
                                        'bg-primary font-semibold text-primary-foreground hover:bg-primary/90':
                                            day === today,
                                    },
                                )}
                                aria-label={`Open ${formatDateKey(day, { weekday: 'long', month: 'long', day: 'numeric' })}`}
                            >
                                {Number(day.slice(8))}
                            </button>
                            <ul className="space-y-0.5">
                                {events.slice(0, 3).map((event) => (
                                    <li key={event.id}>
                                        <Link
                                            href={showEvent(event.id)}
                                            className={cn(
                                                'block truncate rounded border-l-2 px-1 py-0.5 text-[11px] leading-tight hover:opacity-80',
                                                eventTypeClasses[event.type],
                                                {
                                                    'line-through opacity-60':
                                                        event.status ===
                                                        'cancelled',
                                                },
                                            )}
                                            title={`${event.title} · ${formatTimeRange(event.starts_at, event.ends_at, timezone)}`}
                                        >
                                            {!event.all_day && (
                                                <span className="font-medium tabular-nums">
                                                    {formatTime(
                                                        event.starts_at,
                                                        timezone,
                                                    )}{' '}
                                                </span>
                                            )}
                                            {event.title}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            {events.length > 3 && (
                                <button
                                    type="button"
                                    onClick={() => onOpenDay(day)}
                                    className="mt-0.5 text-[11px] text-muted-foreground hover:text-foreground"
                                >
                                    +{events.length - 3} more
                                </button>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

type PositionedEvent = {
    event: CalendarEvent;
    top: number;
    height: number;
    lane: number;
    lanes: number;
};

/**
 * Position timed events within one day, placing overlapping events in side-by-side lanes.
 */
function layoutDay(
    day: string,
    events: CalendarEvent[],
    timezone: string,
): PositionedEvent[] {
    const timed = events
        .filter((event) => !event.all_day)
        .map((event) => {
            const start =
                dateKey(event.starts_at, timezone) < day
                    ? 0
                    : minutesIntoDay(event.starts_at, timezone);
            const endsLater = dateKey(event.ends_at, timezone) > day;
            const end = endsLater
                ? 1440
                : Math.max(start + 15, minutesIntoDay(event.ends_at, timezone));

            return { event, start, end };
        })
        .sort((a, b) => a.start - b.start || b.end - a.end);

    const positioned: PositionedEvent[] = [];
    let cluster: ((typeof timed)[number] & { lane: number })[] = [];
    let clusterEnd = -1;

    const flush = () => {
        const lanes = Math.max(1, ...cluster.map((item) => item.lane + 1));

        for (const item of cluster) {
            positioned.push({
                event: item.event,
                top: (item.start / 60) * HOUR_HEIGHT,
                height: Math.max(
                    20,
                    ((item.end - item.start) / 60) * HOUR_HEIGHT - 2,
                ),
                lane: item.lane,
                lanes,
            });
        }

        cluster = [];
        clusterEnd = -1;
    };

    for (const item of timed) {
        if (item.start >= clusterEnd && cluster.length > 0) {
            flush();
        }

        const usedLanes = cluster
            .filter((other) => other.end > item.start)
            .map((other) => other.lane);
        let lane = 0;
        while (usedLanes.includes(lane)) {
            lane++;
        }

        cluster.push({ ...item, lane });
        clusterEnd = Math.max(clusterEnd, item.end);
    }

    if (cluster.length > 0) {
        flush();
    }

    return positioned;
}

function TimeGridView({
    days,
    today,
    eventsByDay,
    timezone,
    canCreate,
    filters,
}: CalendarProps & {
    eventsByDay: Map<string, CalendarEvent[]>;
    timezone: string;
}) {
    const scroller = useRef<HTMLDivElement>(null);
    const [nowMs, setNowMs] = useState(() => Date.now());

    useEffect(() => {
        scroller.current?.scrollTo({ top: 7 * HOUR_HEIGHT });
        const timer = window.setInterval(() => setNowMs(Date.now()), 60_000);

        return () => window.clearInterval(timer);
    }, []);

    const nowIso = new Date(nowMs).toISOString();
    const nowMinutes = minutesIntoDay(nowIso, timezone);
    const hours = Array.from({ length: 24 }, (_, hour) => hour);
    const allDayEvents = days.map((day) =>
        (eventsByDay.get(day) ?? []).filter((event) => event.all_day),
    );
    const hasAllDay = allDayEvents.some((events) => events.length > 0);

    return (
        <div className="overflow-hidden rounded-xl border">
            <div className="overflow-x-auto">
                <div style={{ minWidth: days.length > 1 ? 720 : undefined }}>
                    <div
                        className="grid border-b bg-muted/40"
                        style={{
                            gridTemplateColumns: `4rem repeat(${days.length}, minmax(0, 1fr))`,
                        }}
                    >
                        <div />
                        {days.map((day) => (
                            <div
                                key={day}
                                className="border-l px-2 py-2 text-center"
                            >
                                <div className="text-xs text-muted-foreground">
                                    {formatDateKey(day, { weekday: 'short' })}
                                </div>
                                <div
                                    className={cn(
                                        'mx-auto inline-flex size-7 items-center justify-center rounded-full text-sm font-semibold',
                                        {
                                            'bg-primary text-primary-foreground':
                                                day === today,
                                        },
                                    )}
                                >
                                    {Number(day.slice(8))}
                                </div>
                            </div>
                        ))}
                    </div>

                    {hasAllDay && (
                        <div
                            className="grid border-b"
                            style={{
                                gridTemplateColumns: `4rem repeat(${days.length}, minmax(0, 1fr))`,
                            }}
                        >
                            <div className="px-1 py-1.5 text-right text-[10px] text-muted-foreground">
                                All day
                            </div>
                            {allDayEvents.map((events, index) => (
                                <div
                                    key={days[index]}
                                    className="space-y-0.5 border-l p-1"
                                >
                                    {events.map((event) => (
                                        <Link
                                            key={event.id}
                                            href={showEvent(event.id)}
                                            className={cn(
                                                'block truncate rounded border-l-2 px-1 py-0.5 text-xs',
                                                eventTypeClasses[event.type],
                                            )}
                                        >
                                            {event.title}
                                        </Link>
                                    ))}
                                </div>
                            ))}
                        </div>
                    )}

                    <div
                        ref={scroller}
                        className="max-h-[70vh] overflow-y-auto"
                    >
                        <div
                            className="relative grid"
                            style={{
                                gridTemplateColumns: `4rem repeat(${days.length}, minmax(0, 1fr))`,
                            }}
                        >
                            <div>
                                {hours.map((hour) => (
                                    <div
                                        key={hour}
                                        className="relative pr-2 text-right text-[10px] text-muted-foreground"
                                        style={{ height: HOUR_HEIGHT }}
                                    >
                                        <span className="relative -top-1.5">
                                            {hour === 0
                                                ? ''
                                                : formatDateKeyHour(hour)}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            {days.map((day) => (
                                <div
                                    key={day}
                                    className="relative border-l"
                                    style={{ height: HOUR_HEIGHT * 24 }}
                                >
                                    {hours.map((hour) => {
                                        const time = `${String(hour).padStart(2, '0')}:00`;

                                        return canCreate ? (
                                            <Link
                                                key={hour}
                                                href={createEvent({
                                                    query: {
                                                        date: day,
                                                        time,
                                                        executive:
                                                            filters.executive ||
                                                            undefined,
                                                    },
                                                })}
                                                className="block border-b border-dashed border-border/60 hover:bg-accent/40"
                                                style={{ height: HOUR_HEIGHT }}
                                                aria-label={`New appointment on ${formatDateKey(day)} at ${time}`}
                                                tabIndex={-1}
                                            />
                                        ) : (
                                            <div
                                                key={hour}
                                                className="border-b border-dashed border-border/60"
                                                style={{ height: HOUR_HEIGHT }}
                                            />
                                        );
                                    })}

                                    {layoutDay(
                                        day,
                                        eventsByDay.get(day) ?? [],
                                        timezone,
                                    ).map(
                                        ({
                                            event,
                                            top,
                                            height,
                                            lane,
                                            lanes,
                                        }) => (
                                            <Link
                                                key={event.id}
                                                href={showEvent(event.id)}
                                                className={cn(
                                                    'absolute overflow-hidden rounded-md border-l-4 px-1.5 py-1 text-xs shadow-xs hover:z-10 hover:shadow-md',
                                                    eventTypeClasses[
                                                        event.type
                                                    ],
                                                    {
                                                        'line-through opacity-60':
                                                            event.status ===
                                                            'cancelled',
                                                    },
                                                )}
                                                style={{
                                                    top,
                                                    height,
                                                    left: `calc(${(lane / lanes) * 100}% + 2px)`,
                                                    width: `calc(${100 / lanes}% - 4px)`,
                                                }}
                                                title={`${event.title} · ${formatTimeRange(event.starts_at, event.ends_at, timezone)}`}
                                            >
                                                <span className="block truncate font-medium">
                                                    {event.title}
                                                </span>
                                                {height > 30 && (
                                                    <span className="block truncate text-muted-foreground">
                                                        {formatTime(
                                                            event.starts_at,
                                                            timezone,
                                                        )}
                                                        {event.location &&
                                                            ` · ${event.location}`}
                                                    </span>
                                                )}
                                            </Link>
                                        ),
                                    )}

                                    {day === today && (
                                        <div
                                            className="pointer-events-none absolute inset-x-0 z-20 border-t-2 border-red-500"
                                            style={{
                                                top:
                                                    (nowMinutes / 60) *
                                                    HOUR_HEIGHT,
                                            }}
                                            aria-hidden
                                        >
                                            <span className="absolute -top-1.5 -left-1 size-2.5 rounded-full bg-red-500" />
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Label an hour of the day, e.g. "7 AM".
 */
function formatDateKeyHour(hour: number): string {
    const suffix = hour < 12 ? 'AM' : 'PM';
    const display = hour % 12 === 0 ? 12 : hour % 12;

    return `${display} ${suffix}`;
}

function AgendaView({
    days,
    eventsByDay,
    timezone,
    today,
}: {
    days: string[];
    eventsByDay: Map<string, CalendarEvent[]>;
    timezone: string;
    today: string;
}) {
    const daysWithEvents = days.filter(
        (day) => (eventsByDay.get(day) ?? []).length > 0,
    );

    if (daysWithEvents.length === 0) {
        return (
            <EmptyState
                icon={CalendarDays}
                title="No appointments in this period"
                description="Try another date range or clear the filters."
            />
        );
    }

    return (
        <div className="space-y-4">
            {daysWithEvents.map((day) => (
                <section
                    key={day}
                    className="overflow-hidden rounded-xl border"
                >
                    <h2
                        className={cn(
                            'border-b bg-muted/40 px-4 py-2 text-sm font-semibold',
                            { 'text-primary': day === today },
                        )}
                    >
                        {formatDateKey(day, {
                            weekday: 'long',
                            month: 'long',
                            day: 'numeric',
                            year: 'numeric',
                        })}
                        {day === today && (
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                Today
                            </span>
                        )}
                    </h2>
                    <ul className="divide-y">
                        {(eventsByDay.get(day) ?? []).map((event) => (
                            <li key={event.id}>
                                <Link
                                    href={showEvent(event.id)}
                                    className="flex flex-col gap-1 px-4 py-3 hover:bg-accent/40 sm:flex-row sm:items-center sm:gap-4"
                                >
                                    <span className="w-44 shrink-0 text-sm text-muted-foreground tabular-nums">
                                        {event.all_day
                                            ? 'All day'
                                            : formatTimeRange(
                                                  event.starts_at,
                                                  event.ends_at,
                                                  timezone,
                                              )}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={cn(
                                                'block truncate font-medium',
                                                {
                                                    'line-through':
                                                        event.status ===
                                                        'cancelled',
                                                },
                                            )}
                                        >
                                            {event.title}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
                                            <span>{event.type_label}</span>
                                            {event.owner && (
                                                <span>{event.owner.name}</span>
                                            )}
                                            {event.location && (
                                                <span className="flex items-center gap-1">
                                                    <MapPin
                                                        className="size-3"
                                                        aria-hidden
                                                    />
                                                    {event.location}
                                                </span>
                                            )}
                                            {event.meeting_url && (
                                                <span className="flex items-center gap-1">
                                                    <Video
                                                        className="size-3"
                                                        aria-hidden
                                                    />
                                                    Online
                                                </span>
                                            )}
                                        </span>
                                    </span>
                                    <span className="flex shrink-0 gap-2">
                                        <PriorityBadge
                                            priority={event.priority}
                                        />
                                        {event.status !== 'scheduled' && (
                                            <StatusBadge
                                                status={event.status}
                                            />
                                        )}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Calendar', href: calendar() }],
};
