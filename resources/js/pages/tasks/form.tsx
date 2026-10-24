import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime, toLocalInput } from '@/lib/datetime';
import { index, show, store, update } from '@/routes/tasks';
import type { Option, Task, UserOption } from '@/types';

type TaskFormData = {
    title: string;
    description: string;
    assigned_to: string;
    calendar_event_id: string;
    project: string;
    priority: string;
    timezone: string;
    starts_at: string;
    due_at: string;
    estimated_minutes: string;
    notes: string;
    recurrence_frequency: string;
    recurrence_interval: string;
    recurrence_ends_on: string;
    checklist: string[];
};

type TaskFormProps = {
    task: { data: Task } | null;
    prefill: { event?: number; assignee?: number };
    options: {
        users: UserOption[];
        priorities: Option[];
        frequencies: Option[];
        timezones: string[];
        events: { id: number; title: string; starts_at: string }[];
    };
};

export default function TaskForm({
    task: wrapped,
    prefill,
    options,
}: TaskFormProps) {
    const { timezone: userTimezone } = useAuth();
    const task = wrapped?.data ?? null;
    const timezone = task?.timezone ?? userTimezone;

    const form = useForm<TaskFormData>({
        title: task?.title ?? '',
        description: task?.description ?? '',
        assigned_to: String(task?.assignee?.id ?? prefill.assignee ?? ''),
        calendar_event_id: String(
            task?.calendar_event_id ?? prefill.event ?? '',
        ),
        project: task?.project ?? '',
        priority: task?.priority ?? 'normal',
        timezone,
        starts_at: toLocalInput(task?.starts_at, timezone),
        due_at: toLocalInput(task?.due_at, timezone),
        estimated_minutes: task?.estimated_minutes
            ? String(task.estimated_minutes)
            : '',
        notes: task?.notes ?? '',
        recurrence_frequency: task?.recurrence_frequency ?? '',
        recurrence_interval: String(task?.recurrence_interval ?? 1),
        recurrence_ends_on: task?.recurrence_ends_on ?? '',
        checklist: [],
    });

    const errors = form.errors as Record<string, string | undefined>;
    const isOccurrence =
        task?.recurrence_parent_id !== null &&
        task?.recurrence_parent_id !== undefined;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            assigned_to: data.assigned_to || null,
            calendar_event_id: data.calendar_event_id || null,
            estimated_minutes: data.estimated_minutes || null,
            starts_at: data.starts_at || null,
            due_at: data.due_at || null,
            recurrence_frequency: data.recurrence_frequency || null,
            recurrence_interval: data.recurrence_frequency
                ? data.recurrence_interval
                : null,
            recurrence_ends_on:
                data.recurrence_frequency && data.recurrence_ends_on
                    ? data.recurrence_ends_on
                    : null,
            checklist: data.checklist.filter((item) => item.trim() !== ''),
        }));

        if (task) {
            form.put(update.url(task.id), { preserveScroll: true });
        } else {
            form.post(store.url(), { preserveScroll: true });
        }
    };

    const title = task ? `Edit ${task.title}` : 'New task';

    return (
        <>
            <Head title={title} />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                noValidate
            >
                <PageHeader
                    title={title}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={task ? show(task.id) : index()}>
                                    Cancel
                                </Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {task ? 'Save changes' : 'Create task'}
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 xl:grid-cols-3">
                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Task</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="title"
                                label="Title"
                                error={errors.title}
                                required
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    maxLength={255}
                                    autoFocus={!task}
                                />
                            </FormField>
                            <FormField
                                id="assigned_to"
                                label="Assigned to"
                                error={errors.assigned_to}
                            >
                                <NativeSelect
                                    id="assigned_to"
                                    value={form.data.assigned_to}
                                    onChange={(e) =>
                                        form.setData(
                                            'assigned_to',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Unassigned</option>
                                    {options.users.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="priority"
                                label="Priority"
                                error={errors.priority}
                                required
                            >
                                <NativeSelect
                                    id="priority"
                                    value={form.data.priority}
                                    onChange={(e) =>
                                        form.setData('priority', e.target.value)
                                    }
                                >
                                    {options.priorities.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="project"
                                label="Project"
                                error={errors.project}
                            >
                                <Input
                                    id="project"
                                    value={form.data.project}
                                    onChange={(e) =>
                                        form.setData('project', e.target.value)
                                    }
                                    maxLength={255}
                                />
                            </FormField>
                            <FormField
                                id="calendar_event_id"
                                label="Related meeting"
                                error={errors.calendar_event_id}
                            >
                                <NativeSelect
                                    id="calendar_event_id"
                                    value={form.data.calendar_event_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'calendar_event_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">None</option>
                                    {options.events.map((event) => (
                                        <option key={event.id} value={event.id}>
                                            {event.title} —{' '}
                                            {formatDateTime(
                                                event.starts_at,
                                                userTimezone,
                                            )}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="description"
                                label="Description"
                                error={errors.description}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    rows={4}
                                />
                            </FormField>
                            <FormField
                                id="notes"
                                label="Notes"
                                error={errors.notes}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    rows={3}
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Schedule
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <FormField
                                    id="timezone"
                                    label="Time zone"
                                    error={errors.timezone}
                                    required
                                >
                                    <NativeSelect
                                        id="timezone"
                                        value={form.data.timezone}
                                        onChange={(e) =>
                                            form.setData(
                                                'timezone',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {options.timezones.map((zone) => (
                                            <option key={zone} value={zone}>
                                                {zone}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id="starts_at"
                                    label="Start"
                                    error={errors.starts_at}
                                >
                                    <Input
                                        id="starts_at"
                                        type="datetime-local"
                                        value={form.data.starts_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'starts_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="due_at"
                                    label="Due"
                                    error={errors.due_at}
                                >
                                    <Input
                                        id="due_at"
                                        type="datetime-local"
                                        value={form.data.due_at}
                                        min={form.data.starts_at || undefined}
                                        onChange={(e) =>
                                            form.setData(
                                                'due_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="estimated_minutes"
                                    label="Estimated duration (minutes)"
                                    error={errors.estimated_minutes}
                                >
                                    <Input
                                        id="estimated_minutes"
                                        type="number"
                                        min={1}
                                        max={10080}
                                        value={form.data.estimated_minutes}
                                        onChange={(e) =>
                                            form.setData(
                                                'estimated_minutes',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </CardContent>
                        </Card>

                        {!isOccurrence && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Repeat
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <FormField
                                        id="recurrence_frequency"
                                        label="Frequency"
                                        error={errors.recurrence_frequency}
                                        hint="Occurrences are created a week ahead and keep the same local due time."
                                    >
                                        <NativeSelect
                                            id="recurrence_frequency"
                                            value={
                                                form.data.recurrence_frequency
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'recurrence_frequency',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Does not repeat
                                            </option>
                                            {options.frequencies.map(
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
                                    </FormField>
                                    {form.data.recurrence_frequency &&
                                        form.data.recurrence_frequency !==
                                            'weekdays' && (
                                            <FormField
                                                id="recurrence_interval"
                                                label="Every"
                                                error={
                                                    errors.recurrence_interval
                                                }
                                            >
                                                <Input
                                                    id="recurrence_interval"
                                                    type="number"
                                                    min={1}
                                                    max={30}
                                                    value={
                                                        form.data
                                                            .recurrence_interval
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'recurrence_interval',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                        )}
                                    {form.data.recurrence_frequency && (
                                        <FormField
                                            id="recurrence_ends_on"
                                            label="Ends on"
                                            error={errors.recurrence_ends_on}
                                        >
                                            <Input
                                                id="recurrence_ends_on"
                                                type="date"
                                                value={
                                                    form.data.recurrence_ends_on
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'recurrence_ends_on',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {!task && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="text-base">
                                        Checklist
                                    </CardTitle>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            form.setData('checklist', [
                                                ...form.data.checklist,
                                                '',
                                            ])
                                        }
                                    >
                                        <Plus />
                                        Add
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {form.data.checklist.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Break the task into steps.
                                        </p>
                                    )}
                                    {form.data.checklist.map(
                                        (item, position) => (
                                            <div
                                                key={position}
                                                className="flex gap-2"
                                            >
                                                <Input
                                                    aria-label={`Checklist item ${position + 1}`}
                                                    value={item}
                                                    maxLength={500}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'checklist',
                                                            form.data.checklist.map(
                                                                (
                                                                    value,
                                                                    index,
                                                                ) =>
                                                                    index ===
                                                                    position
                                                                        ? e
                                                                              .target
                                                                              .value
                                                                        : value,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        form.setData(
                                                            'checklist',
                                                            form.data.checklist.filter(
                                                                (_, index) =>
                                                                    index !==
                                                                    position,
                                                            ),
                                                        )
                                                    }
                                                    aria-label={`Remove checklist item ${position + 1}`}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        ),
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </form>
        </>
    );
}

TaskForm.layout = {
    breadcrumbs: [{ title: 'Tasks', href: index() }],
};
