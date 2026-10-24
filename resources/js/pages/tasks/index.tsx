import { Head, Link, router } from '@inertiajs/react';
import { Columns3, List, ListTodo, Plus, Repeat, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { useOfflineQueue } from '@/hooks/use-offline-queue';
import { formatDateTime } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { create, index, show, status as updateStatus } from '@/routes/tasks';
import type { Collection, Option, Paginated, Task, UserOption } from '@/types';

type Filters = {
    tab: string;
    q: string;
    priority: string;
    assignee: string;
    layout: 'list' | 'board';
    sort: string;
};

type TasksIndexProps = {
    tasks: Paginated<Task> | Collection<Task>;
    counts: Record<string, number>;
    filters: Filters;
    options: { priorities: Option[]; statuses: Option[]; users: UserOption[] };
    canCreate: boolean;
};

const TABS: { value: string; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'cancelled', label: 'Cancelled' },
];

const BOARD_COLUMNS: { status: Task['status']; label: string }[] = [
    { status: 'pending', label: 'Pending' },
    { status: 'in_progress', label: 'In progress' },
    { status: 'completed', label: 'Completed' },
];

export default function TasksIndex({
    tasks,
    counts,
    filters,
    options,
    canCreate,
}: TasksIndexProps) {
    const { timezone } = useAuth();
    const [search, setSearch] = useState(filters.q);
    const { changeTaskStatus } = useOfflineQueue();

    const visit = (overrides: Partial<Filters>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...overrides }).filter(
                ([, value]) => value !== '' && value !== undefined,
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

    const setStatus = (task: Task, status: Task['status']) =>
        changeTaskStatus(task, status, () =>
            router.patch(
                updateStatus.url(task.id),
                { status },
                { preserveScroll: true, preserveState: true },
            ),
        );

    const items = tasks.data;

    return (
        <>
            <Head title="Tasks" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Tasks"
                    description="Daily and weekly work, deadlines, and progress."
                    actions={
                        canCreate && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus />
                                    New task
                                </Link>
                            </Button>
                        )
                    }
                />

                <div
                    className="flex flex-wrap gap-1 border-b"
                    role="tablist"
                    aria-label="Task status"
                >
                    {TABS.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            role="tab"
                            aria-selected={filters.tab === tab.value}
                            onClick={() => visit({ tab: tab.value })}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm transition-colors',
                                filters.tab === tab.value
                                    ? 'border-primary font-medium text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                            <span
                                className={cn(
                                    'ml-1.5 rounded-full px-1.5 text-xs tabular-nums',
                                    tab.value === 'overdue' &&
                                        counts.overdue > 0
                                        ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'
                                        : 'bg-muted',
                                )}
                            >
                                {counts[tab.value] ?? 0}
                            </span>
                        </button>
                    ))}
                </div>

                <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <form onSubmit={submitSearch} className="relative">
                            <Search
                                className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search tasks or projects"
                                className="w-64 pl-8"
                                aria-label="Search tasks"
                            />
                        </form>
                        <NativeSelect
                            className="w-auto"
                            value={filters.priority}
                            onChange={(e) =>
                                visit({ priority: e.target.value })
                            }
                            aria-label="Filter by priority"
                        >
                            <option value="">All priorities</option>
                            {options.priorities.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                        {options.users.length > 0 && (
                            <NativeSelect
                                className="w-auto"
                                value={filters.assignee}
                                onChange={(e) =>
                                    visit({ assignee: e.target.value })
                                }
                                aria-label="Filter by assignee"
                            >
                                <option value="">Anyone</option>
                                {options.users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                        <NativeSelect
                            className="w-auto"
                            value={filters.sort}
                            onChange={(e) => visit({ sort: e.target.value })}
                            aria-label="Sort tasks"
                        >
                            <option value="due_at">Sort by due date</option>
                            <option value="priority">Sort by priority</option>
                            <option value="created_at">Newest first</option>
                        </NativeSelect>
                    </div>
                    <div
                        className="inline-flex rounded-md border p-0.5"
                        role="group"
                        aria-label="Layout"
                    >
                        <button
                            type="button"
                            aria-pressed={filters.layout === 'list'}
                            onClick={() => visit({ layout: 'list' })}
                            className={cn(
                                'flex items-center gap-1 rounded px-2.5 py-1 text-sm',
                                filters.layout === 'list'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                            )}
                        >
                            <List className="size-4" aria-hidden />
                            List
                        </button>
                        <button
                            type="button"
                            aria-pressed={filters.layout === 'board'}
                            onClick={() => visit({ layout: 'board' })}
                            className={cn(
                                'flex items-center gap-1 rounded px-2.5 py-1 text-sm',
                                filters.layout === 'board'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                            )}
                        >
                            <Columns3 className="size-4" aria-hidden />
                            Board
                        </button>
                    </div>
                </div>

                {items.length === 0 ? (
                    <EmptyState
                        icon={ListTodo}
                        title="No tasks found"
                        description="Adjust the filters or create a new task."
                    />
                ) : filters.layout === 'board' ? (
                    <div className="grid gap-4 md:grid-cols-3">
                        {BOARD_COLUMNS.map((column) => {
                            const columnTasks = items.filter(
                                (task) => task.status === column.status,
                            );

                            return (
                                <section
                                    key={column.status}
                                    className="flex flex-col gap-2 rounded-xl bg-muted/40 p-3"
                                >
                                    <h2 className="flex items-center justify-between text-sm font-semibold">
                                        {column.label}
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {columnTasks.length}
                                        </span>
                                    </h2>
                                    {columnTasks.map((task) => (
                                        <article
                                            key={task.id}
                                            className="space-y-2 rounded-lg border bg-card p-3 shadow-xs"
                                        >
                                            <Link
                                                href={show(task.id)}
                                                className="block text-sm font-medium hover:underline"
                                            >
                                                {task.title}
                                            </Link>
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <PriorityBadge
                                                    priority={task.priority}
                                                />
                                                {task.is_overdue && (
                                                    <StatusBadge status="overdue" />
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {task.due_at
                                                    ? `Due ${formatDateTime(task.due_at, timezone)}`
                                                    : 'No due date'}
                                                {task.assignee &&
                                                    ` · ${task.assignee.name}`}
                                            </p>
                                            <NativeSelect
                                                className="h-8 text-xs"
                                                value={task.status}
                                                onChange={(e) =>
                                                    setStatus(
                                                        task,
                                                        e.target
                                                            .value as Task['status'],
                                                    )
                                                }
                                                aria-label={`Status of ${task.title}`}
                                            >
                                                {options.statuses.map(
                                                    (option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </article>
                                    ))}
                                </section>
                            );
                        })}
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="w-10 px-3 py-2">
                                            <span className="sr-only">
                                                Done
                                            </span>
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Task
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Priority
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Due
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Assignee
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {items.map((task) => (
                                        <tr
                                            key={task.id}
                                            className="hover:bg-accent/30"
                                        >
                                            <td className="px-3 py-2">
                                                <Checkbox
                                                    checked={
                                                        task.status ===
                                                        'completed'
                                                    }
                                                    disabled={
                                                        task.status ===
                                                        'cancelled'
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setStatus(
                                                            task,
                                                            checked === true
                                                                ? 'completed'
                                                                : 'pending',
                                                        )
                                                    }
                                                    aria-label={`Mark ${task.title} ${task.status === 'completed' ? 'not done' : 'done'}`}
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                <Link
                                                    href={show(task.id)}
                                                    className={cn(
                                                        'font-medium hover:underline',
                                                        {
                                                            'text-muted-foreground line-through':
                                                                task.status ===
                                                                'completed',
                                                        },
                                                    )}
                                                >
                                                    {task.title}
                                                </Link>
                                                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    {task.project}
                                                    {task.recurrence_frequency ||
                                                    task.recurrence_parent_id ? (
                                                        <Repeat
                                                            className="size-3"
                                                            aria-label="Recurring"
                                                        />
                                                    ) : null}
                                                    {(task.checklist_total ??
                                                        0) > 0 && (
                                                        <span>
                                                            {
                                                                task.checklist_completed
                                                            }
                                                            /
                                                            {
                                                                task.checklist_total
                                                            }{' '}
                                                            checklist
                                                        </span>
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2">
                                                <PriorityBadge
                                                    priority={task.priority}
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                <span className="flex gap-1">
                                                    <StatusBadge
                                                        status={task.status}
                                                    />
                                                    {task.is_overdue && (
                                                        <StatusBadge status="overdue" />
                                                    )}
                                                </span>
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-3 py-2 whitespace-nowrap tabular-nums',
                                                    task.is_overdue
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {task.due_at
                                                    ? formatDateTime(
                                                          task.due_at,
                                                          timezone,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {task.assignee?.name ??
                                                    'Unassigned'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {'meta' in tasks && <PaginationNav paginator={tasks} />}
                    </>
                )}
            </div>
        </>
    );
}

TasksIndex.layout = {
    breadcrumbs: [{ title: 'Tasks', href: index() }],
};
