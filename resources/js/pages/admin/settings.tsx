import { Head, router, useForm } from '@inertiajs/react';
import { Activity, CheckCircle2, Mail, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import { humanize } from '@/lib/labels';
import { cn } from '@/lib/utils';
import { edit, testMail, update } from '@/routes/admin/settings';
import type { Option } from '@/types';

type Integration = { configured: boolean; detail: string; from?: string };

type SettingsProps = {
    settings: {
        event_reminder_minutes: number[];
        task_reminder_minutes: number[];
        presentation_reminder_minutes: number[];
        default_timezone: string;
    };
    integrations: Record<'mail' | 'sms' | 'push' | 'queue', Integration>;
    health: {
        deliveries_24h: Record<string, Record<string, number>>;
        failed_deliveries_24h: number;
        reminders: {
            pending: number;
            in_flight: number;
            overdue_pending: number;
            failed_24h: number;
        };
        queue: { pending_jobs: number; failed_jobs: number };
        scheduler_last_run: string | null;
        scheduler_healthy: boolean;
    };
    timezones: string[];
    reminderMinutes: Option<number>[];
};

type MinutesKey =
    | 'event_reminder_minutes'
    | 'task_reminder_minutes'
    | 'presentation_reminder_minutes';

export default function AdminSettings({
    settings,
    integrations,
    health,
    timezones,
    reminderMinutes,
}: SettingsProps) {
    const { timezone } = useAuth();
    const form = useForm(settings);
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    const toggle = (key: MinutesKey, minutes: number, checked: boolean) =>
        form.setData(
            key,
            checked
                ? [...form.data[key], minutes].sort((a, b) => b - a)
                : form.data[key].filter((value) => value !== minutes),
        );

    const healthRows: [string, number | string, boolean][] = [
        [
            'Scheduler last ran',
            health.scheduler_last_run
                ? formatDateTime(health.scheduler_last_run, timezone)
                : 'Never recorded',
            health.scheduler_healthy,
        ],
        ['Reminders waiting', health.reminders.pending, true],
        ['Reminders in flight', health.reminders.in_flight, true],
        [
            'Due but not dispatched (> 5 min)',
            health.reminders.overdue_pending,
            health.reminders.overdue_pending === 0,
        ],
        [
            'Failed reminders (24 h)',
            health.reminders.failed_24h,
            health.reminders.failed_24h === 0,
        ],
        [
            'Failed delivery attempts (24 h)',
            health.failed_deliveries_24h,
            health.failed_deliveries_24h === 0,
        ],
        ['Queued jobs', health.queue.pending_jobs, true],
        [
            'Failed queue jobs',
            health.queue.failed_jobs,
            health.queue.failed_jobs === 0,
        ],
    ];

    return (
        <>
            <Head title="Application settings" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Application settings"
                    description="Global notification defaults, integrations, and delivery health."
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Integrations
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {(
                                Object.entries(integrations) as [
                                    string,
                                    Integration,
                                ][]
                            ).map(([key, integration]) => (
                                <div
                                    key={key}
                                    className="flex items-start gap-3 rounded-md border p-3"
                                >
                                    {integration.configured ? (
                                        <CheckCircle2
                                            className="mt-0.5 size-4 text-emerald-600"
                                            aria-label="Configured"
                                        />
                                    ) : (
                                        <XCircle
                                            className="mt-0.5 size-4 text-orange-600"
                                            aria-label="Not configured"
                                        />
                                    )}
                                    <div className="min-w-0 flex-1 text-sm">
                                        <p className="font-medium">
                                            {key === 'mail'
                                                ? 'Email (SMTP)'
                                                : key === 'sms'
                                                  ? 'SMS'
                                                  : key === 'push'
                                                    ? 'Web Push'
                                                    : 'Queue'}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {integration.detail}
                                        </p>
                                        {integration.from && (
                                            <p className="text-muted-foreground">
                                                From: {integration.from}
                                            </p>
                                        )}
                                    </div>
                                    {key === 'mail' && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    testMail.url(),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Mail />
                                            Send test email
                                        </Button>
                                    )}
                                </div>
                            ))}
                            <p className="text-xs text-muted-foreground">
                                Credentials are read from the server environment
                                (.env) and are never shown here.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Activity className="size-4" aria-hidden />
                                Delivery health
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <dl className="divide-y">
                                {healthRows.map(([label, value, healthy]) => (
                                    <div
                                        key={label}
                                        className="flex items-center justify-between py-1.5 text-sm"
                                    >
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd
                                            className={cn(
                                                'font-medium tabular-nums',
                                                {
                                                    'text-red-600 dark:text-red-400':
                                                        !healthy,
                                                },
                                            )}
                                        >
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <div>
                                <p className="mb-1 text-sm font-medium">
                                    Attempts in the last 24 hours
                                </p>
                                {Object.keys(health.deliveries_24h).length ===
                                0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No delivery attempts.
                                    </p>
                                ) : (
                                    <ul className="space-y-1 text-sm">
                                        {Object.entries(
                                            health.deliveries_24h,
                                        ).map(([channel, counts]) => (
                                            <li
                                                key={channel}
                                                className="flex flex-wrap gap-2"
                                            >
                                                <span className="w-16 font-medium">
                                                    {humanize(channel)}
                                                </span>
                                                {Object.entries(counts).map(
                                                    ([status, count]) => (
                                                        <span
                                                            key={status}
                                                            className="text-muted-foreground"
                                                        >
                                                            {humanize(status)}:{' '}
                                                            {count}
                                                        </span>
                                                    ),
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Notification defaults
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <p className="text-sm text-muted-foreground">
                                Defaults apply to new users. Existing users keep
                                the reminder times in their own settings.
                            </p>
                            {(
                                [
                                    [
                                        'event_reminder_minutes',
                                        'Meeting reminders',
                                    ],
                                    [
                                        'task_reminder_minutes',
                                        'Task deadline reminders',
                                    ],
                                    [
                                        'presentation_reminder_minutes',
                                        'Presentation deadline reminders',
                                    ],
                                ] as [MinutesKey, string][]
                            ).map(([key, label]) => (
                                <fieldset key={key} className="space-y-2">
                                    <legend className="text-sm font-medium">
                                        {label}
                                    </legend>
                                    <div className="flex flex-wrap gap-x-4 gap-y-2">
                                        {reminderMinutes.map((option) => (
                                            <label
                                                key={option.value}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <Checkbox
                                                    checked={form.data[
                                                        key
                                                    ].includes(option.value)}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggle(
                                                            key,
                                                            option.value,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                {option.label.replace(
                                                    ' before',
                                                    '',
                                                )}
                                            </label>
                                        ))}
                                    </div>
                                    <InputError message={errors[key]} />
                                </fieldset>
                            ))}
                            <FormField
                                id="default_timezone"
                                label="Default time zone for new users"
                                error={errors.default_timezone}
                                className="max-w-sm"
                            >
                                <NativeSelect
                                    id="default_timezone"
                                    value={form.data.default_timezone}
                                    onChange={(e) =>
                                        form.setData(
                                            'default_timezone',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {timezones.map((zone) => (
                                        <option key={zone} value={zone}>
                                            {zone}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Save defaults
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}

AdminSettings.layout = {
    breadcrumbs: [{ title: 'Application settings', href: edit() }],
};
