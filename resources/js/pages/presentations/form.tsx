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
import { index, show, store, update } from '@/routes/presentations';
import type { Option, Presentation, UserOption } from '@/types';

type PresentationFormData = {
    owner_id: string;
    title: string;
    description: string;
    calendar_event_id: string;
    presenter_id: string;
    presenter_name: string;
    timezone: string;
    presentation_at: string;
    preparation_deadline_at: string;
    review_deadline_at: string;
    venue: string;
    meeting_url: string;
    status: string;
    notes: string;
    checklist: string[];
};

type PresentationFormProps = {
    presentation: { data: Presentation } | null;
    prefill: { event?: number };
    options: {
        executives: UserOption[];
        users: UserOption[];
        statuses: Option[];
        timezones: string[];
        events: { id: number; title: string; starts_at: string }[];
    };
};

export default function PresentationForm({
    presentation: wrapped,
    prefill,
    options,
}: PresentationFormProps) {
    const { timezone: userTimezone } = useAuth();
    const presentation = wrapped?.data ?? null;
    const timezone =
        presentation?.timezone ??
        options.executives[0]?.timezone ??
        userTimezone;

    const form = useForm<PresentationFormData>({
        owner_id: String(
            presentation?.owner?.id ?? options.executives[0]?.id ?? '',
        ),
        title: presentation?.title ?? '',
        description: presentation?.description ?? '',
        calendar_event_id: String(
            presentation?.calendar_event_id ?? prefill.event ?? '',
        ),
        presenter_id: String(presentation?.presenter?.id ?? ''),
        presenter_name: presentation?.presenter_name ?? '',
        timezone,
        presentation_at: toLocalInput(presentation?.presentation_at, timezone),
        preparation_deadline_at: toLocalInput(
            presentation?.preparation_deadline_at,
            timezone,
        ),
        review_deadline_at: toLocalInput(
            presentation?.review_deadline_at,
            timezone,
        ),
        venue: presentation?.venue ?? '',
        meeting_url: presentation?.meeting_url ?? '',
        status:
            presentation?.status === 'approved'
                ? 'under_review'
                : (presentation?.status ?? 'draft'),
        notes: presentation?.notes ?? '',
        checklist: [],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            calendar_event_id: data.calendar_event_id || null,
            presenter_id: data.presenter_id || null,
            presentation_at: data.presentation_at || null,
            preparation_deadline_at: data.preparation_deadline_at || null,
            review_deadline_at: data.review_deadline_at || null,
            checklist: data.checklist.filter((item) => item.trim() !== ''),
        }));

        if (presentation) {
            form.put(update.url(presentation.id), { preserveScroll: true });
        } else {
            form.post(store.url(), { preserveScroll: true });
        }
    };

    const title = presentation
        ? `Edit ${presentation.title}`
        : 'New presentation';

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
                    description={
                        presentation?.status === 'approved'
                            ? 'Saving changes returns an approved presentation to review.'
                            : undefined
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        presentation
                                            ? show(presentation.id)
                                            : index()
                                    }
                                >
                                    Cancel
                                </Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {presentation
                                    ? 'Save changes'
                                    : 'Create presentation'}
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 xl:grid-cols-3">
                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Presentation
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
                                        form.setData('title', e.target.value)
                                    }
                                    maxLength={255}
                                    autoFocus={!presentation}
                                />
                            </FormField>
                            <FormField
                                id="owner_id"
                                label="Executive"
                                error={errors.owner_id}
                                required
                            >
                                <NativeSelect
                                    id="owner_id"
                                    value={form.data.owner_id}
                                    onChange={(e) =>
                                        form.setData('owner_id', e.target.value)
                                    }
                                >
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
                                id="status"
                                label="Status"
                                error={errors.status}
                                required
                                hint="Approval is recorded with the Approve action."
                            >
                                <NativeSelect
                                    id="status"
                                    value={form.data.status}
                                    onChange={(e) =>
                                        form.setData('status', e.target.value)
                                    }
                                >
                                    {options.statuses.map((option) => (
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
                                id="presenter_id"
                                label="Presenter (colleague)"
                                error={errors.presenter_id}
                            >
                                <NativeSelect
                                    id="presenter_id"
                                    value={form.data.presenter_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'presenter_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        The executive / external presenter
                                    </option>
                                    {options.users.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="presenter_name"
                                label="External presenter"
                                error={errors.presenter_name}
                            >
                                <Input
                                    id="presenter_name"
                                    value={form.data.presenter_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'presenter_name',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={255}
                                />
                            </FormField>
                            <FormField
                                id="calendar_event_id"
                                label="Related appointment"
                                error={errors.calendar_event_id}
                                className="sm:col-span-2"
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
                                    Dates and venue
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
                                    id="presentation_at"
                                    label="Presentation date and time"
                                    error={errors.presentation_at}
                                >
                                    <Input
                                        id="presentation_at"
                                        type="datetime-local"
                                        value={form.data.presentation_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'presentation_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="preparation_deadline_at"
                                    label="Preparation deadline"
                                    error={errors.preparation_deadline_at}
                                >
                                    <Input
                                        id="preparation_deadline_at"
                                        type="datetime-local"
                                        value={
                                            form.data.preparation_deadline_at
                                        }
                                        max={
                                            form.data.presentation_at ||
                                            undefined
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'preparation_deadline_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="review_deadline_at"
                                    label="Review deadline"
                                    error={errors.review_deadline_at}
                                >
                                    <Input
                                        id="review_deadline_at"
                                        type="datetime-local"
                                        value={form.data.review_deadline_at}
                                        max={
                                            form.data.presentation_at ||
                                            undefined
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'review_deadline_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="venue"
                                    label="Venue"
                                    error={errors.venue}
                                >
                                    <Input
                                        id="venue"
                                        value={form.data.venue}
                                        onChange={(e) =>
                                            form.setData(
                                                'venue',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={255}
                                    />
                                </FormField>
                                <FormField
                                    id="meeting_url"
                                    label="Meeting link"
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
                            </CardContent>
                        </Card>

                        {!presentation && (
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
                                            e.g. Draft slides, rehearse, confirm
                                            AV.
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

PresentationForm.layout = {
    breadcrumbs: [{ title: 'Presentations', href: index() }],
};
