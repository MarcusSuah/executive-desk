import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Plus, Trash2, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/hooks/use-auth';
import { addDays, toLocalInput } from '@/lib/datetime';
import { calendar } from '@/routes';
import { show, store, update } from '@/routes/events';
import type { CalendarEvent, Option, UserOption } from '@/types';

type AttendeeRow = {
    user_id: number | null;
    name: string;
    email: string;
    role: string;
};

type EventFormData = {
    owner_id: string;
    title: string;
    description: string;
    type: string;
    category: string;
    priority: string;
    timezone: string;
    all_day: boolean;
    starts_at: string;
    ends_at: string;
    location: string;
    meeting_url: string;
    agenda: string;
    notes: string;
    preparation_notes: string;
    reminder_minutes: number[] | null;
    attendees: AttendeeRow[];
    checklist: string[];
    acknowledge_conflicts: boolean;
    version: number;
};

type EventFormProps = {
    event: { data: CalendarEvent } | null;
    prefill: { date?: string; time?: string; executive?: number };
    options: {
        executives: UserOption[];
        users: UserOption[];
        types: Option[];
        priorities: Option[];
        attendeeRoles: Option[];
        timezones: string[];
        reminderMinutes: Option<number>[];
    };
};

/**
 * Get the next whole hour in a time zone as a datetime-local value.
 */
function nextHour(timezone: string, date?: string, time?: string): string {
    if (date) {
        return `${date}T${time ?? '09:00'}`;
    }

    const local = toLocalInput(
        new Date(Date.now() + 60 * 60 * 1000).toISOString(),
        timezone,
    );

    return `${local.slice(0, 13)}:00`;
}

/**
 * Add minutes to a datetime-local value (pure wall-clock arithmetic).
 */
function addMinutesLocal(value: string, minutes: number): string {
    const date = new Date(`${value}:00Z`);
    date.setUTCMinutes(date.getUTCMinutes() + minutes);

    return date.toISOString().slice(0, 16);
}

export default function EventForm({
    event: wrapped,
    prefill,
    options,
}: EventFormProps) {
    const { timezone: userTimezone } = useAuth();
    const event = wrapped?.data ?? null;
    const defaultOwner =
        options.executives.find(
            (executive) => executive.id === Number(prefill.executive),
        ) ?? options.executives[0];
    const defaultTimezone =
        event?.timezone ?? defaultOwner?.timezone ?? userTimezone;
    const defaultStart = event
        ? toLocalInput(event.starts_at, event.timezone)
        : nextHour(defaultTimezone, prefill.date, prefill.time);

    const form = useForm<EventFormData>({
        owner_id: String(event?.owner?.id ?? defaultOwner?.id ?? ''),
        title: event?.title ?? '',
        description: event?.description ?? '',
        type: event?.type ?? 'internal_meeting',
        category: event?.category ?? '',
        priority: event?.priority ?? 'normal',
        timezone: defaultTimezone,
        all_day: event?.all_day ?? false,
        starts_at: defaultStart,
        ends_at: event
            ? event.all_day
                ? `${addDays(toLocalInput(event.ends_at, event.timezone).slice(0, 10), -1)}T00:00`
                : toLocalInput(event.ends_at, event.timezone)
            : addMinutesLocal(defaultStart, 60),
        location: event?.location ?? '',
        meeting_url: event?.meeting_url ?? '',
        agenda: event?.agenda ?? '',
        notes: event?.notes ?? '',
        preparation_notes: event?.preparation_notes ?? '',
        reminder_minutes: event?.reminder_minutes ?? null,
        attendees: (event?.attendees ?? []).map((attendee) => ({
            user_id: attendee.user_id,
            name: attendee.name,
            email: attendee.email ?? '',
            role: attendee.role,
        })),
        checklist: [],
        acknowledge_conflicts: false,
        version: event?.version ?? 1,
    });

    const errors = form.errors as Record<string, string | undefined>;

    const submit = (submitEvent?: FormEvent, acknowledge = false) => {
        submitEvent?.preventDefault();

        form.transform((data) => ({
            ...data,
            acknowledge_conflicts: acknowledge,
            starts_at: data.all_day
                ? `${data.starts_at.slice(0, 10)}T00:00`
                : data.starts_at,
            ends_at: data.all_day
                ? `${addDays(data.ends_at.slice(0, 10), 1)}T00:00`
                : data.ends_at,
            checklist: data.checklist.filter((item) => item.trim() !== ''),
        }));

        const optionsForVisit = { preserveScroll: true };

        if (event) {
            form.put(update.url(event.id), optionsForVisit);
        } else {
            form.post(store.url(), optionsForVisit);
        }
    };

    const setStart = (value: string) => {
        const duration =
            (new Date(`${form.data.ends_at}:00Z`).getTime() -
                new Date(`${form.data.starts_at}:00Z`).getTime()) /
            60000;
        form.setData((data) => ({
            ...data,
            starts_at: value,
            ends_at:
                value && duration > 0
                    ? addMinutesLocal(value, duration)
                    : data.ends_at,
        }));
    };

    const setOwner = (ownerId: string) => {
        const owner = options.executives.find(
            (executive) => String(executive.id) === ownerId,
        );
        form.setData((data) => ({
            ...data,
            owner_id: ownerId,
            timezone:
                !event && owner?.timezone ? owner.timezone : data.timezone,
        }));
    };

    const updateAttendee = (index: number, changes: Partial<AttendeeRow>) =>
        form.setData(
            'attendees',
            form.data.attendees.map((attendee, position) =>
                position === index ? { ...attendee, ...changes } : attendee,
            ),
        );

    const toggleReminder = (minutes: number, checked: boolean) => {
        const current = form.data.reminder_minutes ?? [];
        form.setData(
            'reminder_minutes',
            checked
                ? [...current, minutes].sort((a, b) => b - a)
                : current.filter((value) => value !== minutes),
        );
    };

    const title = event ? `Edit ${event.title}` : 'New appointment';

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
                    description="Times are entered in the selected time zone and stored in UTC."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={event ? show(event.id) : calendar()}
                                >
                                    Cancel
                                </Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {event
                                    ? 'Save changes'
                                    : 'Schedule appointment'}
                            </Button>
                        </>
                    }
                />

                {errors.conflicts && (
                    <div
                        role="alert"
                        className="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                    >
                        <p className="flex items-start gap-2 text-sm">
                            <AlertTriangle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            {errors.conflicts}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => submit(undefined, true)}
                        >
                            Save anyway
                        </Button>
                    </div>
                )}

                {errors.version && (
                    <div
                        role="alert"
                        className="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-100"
                    >
                        {errors.version}
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Details
                                </CardTitle>
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
                                            form.setData(
                                                'title',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={255}
                                        required
                                        autoFocus={!event}
                                    />
                                </FormField>
                                <FormField
                                    id="owner_id"
                                    label="Executive's calendar"
                                    error={errors.owner_id}
                                    required
                                >
                                    <NativeSelect
                                        id="owner_id"
                                        value={form.data.owner_id}
                                        onChange={(e) =>
                                            setOwner(e.target.value)
                                        }
                                        required
                                    >
                                        <option value="" disabled>
                                            Select an executive
                                        </option>
                                        {options.executives.map((executive) => (
                                            <option
                                                key={executive.id}
                                                value={executive.id}
                                            >
                                                {executive.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id="type"
                                    label="Type"
                                    error={errors.type}
                                    required
                                >
                                    <NativeSelect
                                        id="type"
                                        value={form.data.type}
                                        onChange={(e) =>
                                            form.setData('type', e.target.value)
                                        }
                                    >
                                        {options.types.map((option) => (
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
                                    id="priority"
                                    label="Priority"
                                    error={errors.priority}
                                    required
                                >
                                    <NativeSelect
                                        id="priority"
                                        value={form.data.priority}
                                        onChange={(e) =>
                                            form.setData(
                                                'priority',
                                                e.target.value,
                                            )
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
                                    id="category"
                                    label="Category"
                                    error={errors.category}
                                >
                                    <Input
                                        id="category"
                                        value={form.data.category}
                                        onChange={(e) =>
                                            form.setData(
                                                'category',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={64}
                                        placeholder="e.g. Finance"
                                    />
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
                                        rows={3}
                                    />
                                </FormField>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Date and time
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="timezone"
                                    label="Time zone"
                                    error={errors.timezone}
                                    required
                                    className="sm:col-span-2"
                                    hint="The appointment keeps this time zone; everyone sees it converted to their own."
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
                                <div className="flex items-center gap-2 sm:col-span-2">
                                    <Checkbox
                                        id="all_day"
                                        checked={form.data.all_day}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'all_day',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="all_day">All-day</Label>
                                </div>
                                {form.data.all_day ? (
                                    <>
                                        <FormField
                                            id="starts_at"
                                            label="Start date"
                                            error={errors.starts_at}
                                            required
                                        >
                                            <Input
                                                id="starts_at"
                                                type="date"
                                                value={form.data.starts_at.slice(
                                                    0,
                                                    10,
                                                )}
                                                onChange={(e) =>
                                                    setStart(
                                                        `${e.target.value}T00:00`,
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="ends_at"
                                            label="End date"
                                            error={errors.ends_at}
                                            required
                                        >
                                            <Input
                                                id="ends_at"
                                                type="date"
                                                value={form.data.ends_at.slice(
                                                    0,
                                                    10,
                                                )}
                                                min={form.data.starts_at.slice(
                                                    0,
                                                    10,
                                                )}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'ends_at',
                                                        `${e.target.value}T00:00`,
                                                    )
                                                }
                                            />
                                        </FormField>
                                    </>
                                ) : (
                                    <>
                                        <FormField
                                            id="starts_at"
                                            label="Starts"
                                            error={errors.starts_at}
                                            required
                                        >
                                            <Input
                                                id="starts_at"
                                                type="datetime-local"
                                                value={form.data.starts_at}
                                                onChange={(e) =>
                                                    setStart(e.target.value)
                                                }
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            id="ends_at"
                                            label="Ends"
                                            error={errors.ends_at}
                                            required
                                        >
                                            <Input
                                                id="ends_at"
                                                type="datetime-local"
                                                value={form.data.ends_at}
                                                min={form.data.starts_at}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'ends_at',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </FormField>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Location and preparation
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="location"
                                    label="Venue / location"
                                    error={errors.location}
                                >
                                    <Input
                                        id="location"
                                        value={form.data.location}
                                        onChange={(e) =>
                                            form.setData(
                                                'location',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={255}
                                        placeholder="Conference Room A"
                                    />
                                </FormField>
                                <FormField
                                    id="meeting_url"
                                    label="Video conference link"
                                    error={errors.meeting_url}
                                >
                                    <Input
                                        id="meeting_url"
                                        type="url"
                                        value={form.data.meeting_url}
                                        onChange={(e) =>
                                            form.setData(
                                                'meeting_url',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="https://"
                                    />
                                </FormField>
                                <FormField
                                    id="agenda"
                                    label="Agenda"
                                    error={errors.agenda}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="agenda"
                                        value={form.data.agenda}
                                        onChange={(e) =>
                                            form.setData(
                                                'agenda',
                                                e.target.value,
                                            )
                                        }
                                        rows={4}
                                    />
                                </FormField>
                                <FormField
                                    id="preparation_notes"
                                    label="Preparation notes"
                                    error={errors.preparation_notes}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="preparation_notes"
                                        value={form.data.preparation_notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'preparation_notes',
                                                e.target.value,
                                            )
                                        }
                                        rows={3}
                                    />
                                </FormField>
                                <FormField
                                    id="notes"
                                    label="Meeting notes"
                                    error={errors.notes}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="notes"
                                        value={form.data.notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        rows={3}
                                    />
                                </FormField>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-base">
                                    Attendees
                                </CardTitle>
                                <div className="flex gap-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            form.setData('attendees', [
                                                ...form.data.attendees,
                                                {
                                                    user_id: null,
                                                    name: '',
                                                    email: '',
                                                    role: 'required',
                                                },
                                            ])
                                        }
                                    >
                                        <UserPlus />
                                        Add
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {form.data.attendees.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        The executive is always included. Add
                                        colleagues or external guests.
                                    </p>
                                )}
                                {form.data.attendees.map((attendee, index) => (
                                    <fieldset
                                        key={index}
                                        className="space-y-2 rounded-md border p-3"
                                    >
                                        <legend className="sr-only">
                                            Attendee {index + 1}
                                        </legend>
                                        <div className="flex gap-2">
                                            <NativeSelect
                                                aria-label="Colleague"
                                                value={attendee.user_id ?? ''}
                                                onChange={(e) =>
                                                    updateAttendee(index, {
                                                        user_id: e.target.value
                                                            ? Number(
                                                                  e.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    External guest
                                                </option>
                                                {options.users.map((user) => (
                                                    <option
                                                        key={user.id}
                                                        value={user.id}
                                                    >
                                                        {user.name}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    form.setData(
                                                        'attendees',
                                                        form.data.attendees.filter(
                                                            (_, position) =>
                                                                position !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                                aria-label={`Remove attendee ${index + 1}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                        {attendee.user_id === null && (
                                            <div className="grid gap-2">
                                                <Input
                                                    aria-label="Guest name"
                                                    placeholder="Name"
                                                    value={attendee.name}
                                                    onChange={(e) =>
                                                        updateAttendee(index, {
                                                            name: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                                <Input
                                                    aria-label="Guest email"
                                                    type="email"
                                                    placeholder="Email (optional)"
                                                    value={attendee.email}
                                                    onChange={(e) =>
                                                        updateAttendee(index, {
                                                            email: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                        )}
                                        <NativeSelect
                                            aria-label="Attendee role"
                                            value={attendee.role}
                                            onChange={(e) =>
                                                updateAttendee(index, {
                                                    role: e.target.value,
                                                })
                                            }
                                        >
                                            {options.attendeeRoles.map(
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
                                        <InputError
                                            message={
                                                errors[
                                                    `attendees.${index}.name`
                                                ] ??
                                                errors[
                                                    `attendees.${index}.email`
                                                ] ??
                                                errors[
                                                    `attendees.${index}.user_id`
                                                ]
                                            }
                                        />
                                    </fieldset>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Reminders
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="custom_reminders"
                                        checked={
                                            form.data.reminder_minutes !== null
                                        }
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'reminder_minutes',
                                                checked === true
                                                    ? [60, 30, 15, 5]
                                                    : null,
                                            )
                                        }
                                    />
                                    <Label htmlFor="custom_reminders">
                                        Use custom reminder times
                                    </Label>
                                </div>
                                {form.data.reminder_minutes === null ? (
                                    <p className="text-sm text-muted-foreground">
                                        Each recipient gets reminders at the
                                        times set in their notification
                                        preferences.
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-2 gap-2">
                                        {options.reminderMinutes.map(
                                            (option) => (
                                                <label
                                                    key={option.value}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        checked={
                                                            form.data.reminder_minutes?.includes(
                                                                option.value,
                                                            ) ?? false
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleReminder(
                                                                option.value,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    {option.label}
                                                </label>
                                            ),
                                        )}
                                    </div>
                                )}
                                <InputError message={errors.reminder_minutes} />
                            </CardContent>
                        </Card>

                        {!event && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="text-base">
                                        Preparation checklist
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
                                            e.g. Print the board pack, confirm
                                            the room.
                                        </p>
                                    )}
                                    {form.data.checklist.map((item, index) => (
                                        <div key={index} className="flex gap-2">
                                            <Input
                                                aria-label={`Checklist item ${index + 1}`}
                                                value={item}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'checklist',
                                                        form.data.checklist.map(
                                                            (
                                                                value,
                                                                position,
                                                            ) =>
                                                                position ===
                                                                index
                                                                    ? e.target
                                                                          .value
                                                                    : value,
                                                        ),
                                                    )
                                                }
                                                maxLength={500}
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    form.setData(
                                                        'checklist',
                                                        form.data.checklist.filter(
                                                            (_, position) =>
                                                                position !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                                aria-label={`Remove checklist item ${index + 1}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </form>
        </>
    );
}

EventForm.layout = {
    breadcrumbs: [{ title: 'Calendar', href: calendar() }],
};
