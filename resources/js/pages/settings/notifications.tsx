import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Bell,
    BellRing,
    Mail,
    MessageSquare,
    Monitor,
    MoonStar,
    Newspaper,
    Smartphone,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAuth } from '@/hooks/use-auth';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import { formatDateTime } from '@/lib/datetime';
import { humanize } from '@/lib/labels';
import { cn } from '@/lib/utils';
import { edit, update } from '@/routes/notifications';
import { history } from '@/routes/notifications';
import {
    destroy as destroyPhone,
    send as sendCode,
    verify as verifyCode,
} from '@/routes/phone';
import {
    destroy as destroyDevice,
    test as testPush,
} from '@/routes/push-subscriptions';
import type { Collection, NotificationDelivery, Option } from '@/types';

type Channel = 'in_app' | 'mail' | 'sms' | 'push';

type Priority = 'high' | 'medium' | 'low';

type CategorySettings = Record<Channel, boolean> & { priority: Priority };

type Preferences = {
    in_app_enabled: boolean;
    email_enabled: boolean;
    sms_enabled: boolean;
    push_enabled: boolean;
    categories: Record<string, CategorySettings>;
    event_reminder_minutes: number[];
    task_reminder_minutes: number[];
    presentation_reminder_minutes: number[];
    daily_briefing_time: string;
    weekly_briefing_day: number;
    weekly_briefing_time: string;
    quiet_hours_enabled: boolean;
    quiet_hours_start: string;
    quiet_hours_end: string;
    quiet_hours_allow_high: boolean;
    digest_frequency: 'off' | 'daily' | 'weekly';
    digest_time: string;
    digest_day: number;
    digest_include: 'non_critical' | 'low_only';
};

type Category = {
    key: string;
    label: string;
    description: string;
    digestible: boolean;
    schedule: 'daily' | 'weekly' | null;
};

type ChannelStatus = { configured: boolean; detail: string | null };

type NotificationSettingsProps = {
    preferences: Preferences;
    categories: Category[];
    pendingDigestItems: number;
    contact: {
        email: string;
        email_verified: boolean;
        phone: string | null;
        phone_verified: boolean;
    };
    channels: { mail: ChannelStatus; sms: ChannelStatus; push: ChannelStatus };
    devices: {
        id: number;
        device_name: string | null;
        created_at: string | null;
        last_used_at: string | null;
    }[];
    recentDeliveries: Collection<NotificationDelivery>;
    reminderMinutes: Option<number>[];
};

const WEEKDAYS = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];

const CATEGORY_CHANNELS: { value: Channel; label: string }[] = [
    { value: 'in_app', label: 'In-App' },
    { value: 'mail', label: 'Email' },
    { value: 'sms', label: 'SMS' },
    { value: 'push', label: 'Push' },
];

const PRIORITIES: { value: Priority; label: string }[] = [
    { value: 'high', label: 'High' },
    { value: 'medium', label: 'Medium' },
    { value: 'low', label: 'Low' },
];

const MASTER_KEYS = {
    in_app: 'in_app_enabled',
    mail: 'email_enabled',
    sms: 'sms_enabled',
    push: 'push_enabled',
} as const satisfies Record<Channel, keyof Preferences>;

type MinutesKey =
    | 'event_reminder_minutes'
    | 'task_reminder_minutes'
    | 'presentation_reminder_minutes';

export default function NotificationSettings({
    preferences,
    categories,
    pendingDigestItems,
    contact,
    channels,
    devices,
    recentDeliveries,
    reminderMinutes,
}: NotificationSettingsProps) {
    const { timezone } = useAuth();
    const push = usePushNotifications();
    const form = useForm<Preferences>(preferences);
    const phoneForm = useForm({ phone: contact.phone ?? '' });
    const codeForm = useForm({ code: '' });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    const toggleMinutes = (
        key: MinutesKey,
        minutes: number,
        checked: boolean,
    ) =>
        form.setData(
            key,
            checked
                ? [...form.data[key], minutes].sort((a, b) => b - a)
                : form.data[key].filter((value) => value !== minutes),
        );

    const setCategory = (key: string, changes: Partial<CategorySettings>) =>
        form.setData('categories', {
            ...form.data.categories,
            [key]: { ...form.data.categories[key], ...changes },
        });

    const minutesGroups: { key: MinutesKey; label: string }[] = [
        { key: 'event_reminder_minutes', label: 'Meetings and appointments' },
        { key: 'task_reminder_minutes', label: 'Task deadlines' },
        {
            key: 'presentation_reminder_minutes',
            label: 'Presentation deadlines',
        },
    ];

    const smsAvailable = contact.phone_verified && channels.sms.configured;

    const channelTiles: {
        channel: Channel;
        icon: typeof Mail;
        label: string;
        description: string;
        status?: ChannelStatus;
        disabled: boolean;
    }[] = [
        {
            channel: 'in_app',
            icon: Bell,
            label: 'In-App',
            description: 'Alerts in the notification bell.',
            disabled: false,
        },
        {
            channel: 'mail',
            icon: Mail,
            label: 'Email',
            description: contact.email,
            status: channels.mail,
            disabled: false,
        },
        {
            channel: 'sms',
            icon: MessageSquare,
            label: 'SMS',
            description: contact.phone_verified
                ? (contact.phone ?? '')
                : 'Verify a mobile number below first.',
            status: channels.sms,
            disabled: !smsAvailable,
        },
        {
            channel: 'push',
            icon: Smartphone,
            label: 'Push',
            description:
                devices.length > 0
                    ? `${devices.length} registered device(s)`
                    : 'Enable push on a device below first.',
            status: channels.push,
            disabled: devices.length === 0,
        },
    ];

    return (
        <>
            <Head title="Notification preferences" />

            <form onSubmit={submit} className="space-y-8">
                <PageHeader
                    title="Notification preferences"
                    description="Choose how and when you receive alerts and updates."
                    actions={
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save preferences
                        </Button>
                    }
                />

                <section
                    aria-label="Notification channels"
                    className="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4"
                >
                    {channelTiles.map((tile) => {
                        const key = MASTER_KEYS[tile.channel];
                        const checked = form.data[key];

                        return (
                            <div key={tile.channel} className="space-y-1">
                                <div className="flex h-full items-start gap-3 rounded-xl border bg-card p-4 shadow-xs">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <tile.icon
                                            className="size-5"
                                            aria-hidden
                                        />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <span
                                            id={`channel-${tile.channel}`}
                                            className="flex items-center gap-2 text-sm font-medium"
                                        >
                                            {tile.label}
                                            {tile.status &&
                                                !tile.status.configured && (
                                                    <StatusBadge
                                                        status="unavailable"
                                                        label="Not configured"
                                                    />
                                                )}
                                        </span>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {tile.description}
                                        </p>
                                    </div>
                                    <Switch
                                        aria-labelledby={`channel-${tile.channel}`}
                                        checked={checked}
                                        disabled={tile.disabled && !checked}
                                        onCheckedChange={(value) =>
                                            form.setData(key, value)
                                        }
                                    />
                                </div>
                                <InputError message={errors[key]} />
                            </div>
                        );
                    })}
                </section>

                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="border-b p-4">
                        <h2 className="text-base font-semibold">
                            Alert categories
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Choose the channels for each kind of alert. A
                            channel that is turned off above stays off for every
                            category.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-2.5">
                                        Alert category
                                    </th>
                                    {CATEGORY_CHANNELS.map((channel) => (
                                        <th
                                            key={channel.value}
                                            scope="col"
                                            className="w-20 px-2 py-2.5 text-center"
                                        >
                                            {channel.label}
                                        </th>
                                    ))}
                                    <th
                                        scope="col"
                                        className="w-56 px-4 py-2.5"
                                    >
                                        Priority / Schedule
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {categories.map((category) => {
                                    const settings =
                                        form.data.categories[category.key];

                                    return (
                                        <tr key={category.key}>
                                            <th
                                                scope="row"
                                                className="px-4 py-3 text-left font-normal"
                                            >
                                                <span className="block font-medium">
                                                    {category.label}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {category.description}
                                                </span>
                                            </th>
                                            {CATEGORY_CHANNELS.map(
                                                (channel) => {
                                                    const masterOn =
                                                        form.data[
                                                            MASTER_KEYS[
                                                                channel.value
                                                            ]
                                                        ];

                                                    return (
                                                        <td
                                                            key={channel.value}
                                                            className="px-2 py-3 text-center"
                                                        >
                                                            <Switch
                                                                aria-label={`${category.label}: ${channel.label}`}
                                                                className={cn(
                                                                    !masterOn &&
                                                                        'opacity-40',
                                                                )}
                                                                checked={
                                                                    settings[
                                                                        channel
                                                                            .value
                                                                    ]
                                                                }
                                                                onCheckedChange={(
                                                                    value,
                                                                ) =>
                                                                    setCategory(
                                                                        category.key,
                                                                        {
                                                                            [channel.value]:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                    );
                                                },
                                            )}
                                            <td className="px-4 py-3">
                                                <CategorySchedule
                                                    category={category}
                                                    settings={settings}
                                                    data={form.data}
                                                    onPriority={(priority) =>
                                                        setCategory(
                                                            category.key,
                                                            { priority },
                                                        )
                                                    }
                                                    onChange={(key, value) =>
                                                        form.setData(
                                                            key,
                                                            value as never,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `categories.${category.key}.priority`
                                                        ] ??
                                                        (category.schedule ===
                                                        'daily'
                                                            ? errors.daily_briefing_time
                                                            : category.schedule ===
                                                                'weekly'
                                                              ? (errors.weekly_briefing_time ??
                                                                errors.weekly_briefing_day)
                                                              : undefined)
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <InputError
                        className="px-4 pb-3"
                        message={errors.categories}
                    />
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="space-y-4 rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <MoonStar className="size-5" aria-hidden />
                            </span>
                            <div className="min-w-0 flex-1">
                                <h2
                                    id="quiet-hours"
                                    className="text-base font-semibold"
                                >
                                    Quiet hours
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Hold back email, SMS, and push alerts at
                                    night. They still appear in the bell.
                                </p>
                            </div>
                            <Switch
                                aria-labelledby="quiet-hours"
                                checked={form.data.quiet_hours_enabled}
                                onCheckedChange={(value) =>
                                    form.setData('quiet_hours_enabled', value)
                                }
                            />
                        </div>
                        <div
                            className={cn(
                                'grid grid-cols-2 gap-3',
                                !form.data.quiet_hours_enabled && 'opacity-60',
                            )}
                        >
                            <div className="space-y-1.5">
                                <Label htmlFor="quiet_hours_start">From</Label>
                                <Input
                                    id="quiet_hours_start"
                                    type="time"
                                    value={form.data.quiet_hours_start}
                                    disabled={!form.data.quiet_hours_enabled}
                                    onChange={(e) =>
                                        form.setData(
                                            'quiet_hours_start',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.quiet_hours_start}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="quiet_hours_end">To</Label>
                                <Input
                                    id="quiet_hours_end"
                                    type="time"
                                    value={form.data.quiet_hours_end}
                                    disabled={!form.data.quiet_hours_enabled}
                                    onChange={(e) =>
                                        form.setData(
                                            'quiet_hours_end',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.quiet_hours_end} />
                            </div>
                        </div>
                        <label className="flex items-center gap-3 text-sm">
                            <Checkbox
                                checked={form.data.quiet_hours_allow_high}
                                disabled={!form.data.quiet_hours_enabled}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'quiet_hours_allow_high',
                                        checked === true,
                                    )
                                }
                            />
                            Allow high priority alerts during quiet hours
                        </label>
                        <p className="text-xs text-muted-foreground">
                            Times are in your time zone ({timezone}).
                        </p>
                    </section>

                    <section className="space-y-4 rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Newspaper className="size-5" aria-hidden />
                            </span>
                            <div className="min-w-0 flex-1">
                                <h2 className="text-base font-semibold">
                                    Digest
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Get one summary email instead of an email
                                    for every update.
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="digest_frequency">
                                    Email digest
                                </Label>
                                <NativeSelect
                                    id="digest_frequency"
                                    value={form.data.digest_frequency}
                                    onChange={(e) =>
                                        form.setData(
                                            'digest_frequency',
                                            e.target
                                                .value as Preferences['digest_frequency'],
                                        )
                                    }
                                >
                                    <option value="off">Off</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                </NativeSelect>
                                <InputError message={errors.digest_frequency} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="digest_time">Send at</Label>
                                <div className="flex gap-2">
                                    {form.data.digest_frequency ===
                                        'weekly' && (
                                        <NativeSelect
                                            aria-label="Digest day"
                                            value={form.data.digest_day}
                                            onChange={(e) =>
                                                form.setData(
                                                    'digest_day',
                                                    Number(e.target.value),
                                                )
                                            }
                                        >
                                            {WEEKDAYS.map((day, index) => (
                                                <option
                                                    key={day}
                                                    value={index + 1}
                                                >
                                                    {day}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                    <Input
                                        id="digest_time"
                                        type="time"
                                        value={form.data.digest_time}
                                        disabled={
                                            form.data.digest_frequency === 'off'
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'digest_time',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <InputError
                                    message={
                                        errors.digest_time ?? errors.digest_day
                                    }
                                />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="digest_include">Include</Label>
                                <NativeSelect
                                    id="digest_include"
                                    value={form.data.digest_include}
                                    disabled={
                                        form.data.digest_frequency === 'off'
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'digest_include',
                                            e.target
                                                .value as Preferences['digest_include'],
                                        )
                                    }
                                >
                                    <option value="non_critical">
                                        All non-critical alerts (medium and low
                                        priority)
                                    </option>
                                    <option value="low_only">
                                        Low priority alerts only
                                    </option>
                                </NativeSelect>
                                <InputError message={errors.digest_include} />
                            </div>
                        </div>
                        <p className="rounded-md bg-muted p-3 text-xs text-muted-foreground">
                            You will still receive high priority alerts straight
                            away. Reminders and briefings are never held for the
                            digest.
                            {pendingDigestItems > 0 &&
                                ` ${pendingDigestItems} update(s) are waiting for your next digest.`}
                        </p>
                    </section>
                </div>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Reminder times"
                        description="Reminders are sent this long before each start time or deadline. An appointment can override these."
                    />
                    {minutesGroups.map((group) => (
                        <fieldset key={group.key} className="space-y-2">
                            <legend className="text-sm font-medium">
                                {group.label}
                            </legend>
                            <div className="flex flex-wrap gap-x-4 gap-y-2">
                                {reminderMinutes.map((option) => (
                                    <label
                                        key={option.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data[
                                                group.key
                                            ].includes(option.value)}
                                            onCheckedChange={(checked) =>
                                                toggleMinutes(
                                                    group.key,
                                                    option.value,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {option.label.replace(' before', '')}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors[group.key]} />
                        </fieldset>
                    ))}
                </section>

                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save preferences
                </Button>
            </form>

            <section className="space-y-4">
                <Heading
                    variant="small"
                    title="Mobile number"
                    description="SMS is only sent to a verified number."
                />
                {!channels.sms.configured && (
                    <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                        SMS is unavailable: no SMS provider has been configured
                        for this installation.
                    </p>
                )}
                {contact.phone && (
                    <p className="flex items-center gap-2 text-sm">
                        {contact.phone}
                        <StatusBadge
                            status={
                                contact.phone_verified ? 'completed' : 'pending'
                            }
                            label={
                                contact.phone_verified
                                    ? 'Verified'
                                    : 'Not verified'
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.delete(destroyPhone.url(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Remove
                        </Button>
                    </p>
                )}
                <form
                    className="flex flex-wrap items-start gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        phoneForm.post(sendCode.url(), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="space-y-1">
                        <Label htmlFor="phone" className="sr-only">
                            Mobile number
                        </Label>
                        <Input
                            id="phone"
                            type="tel"
                            className="w-56"
                            placeholder="+639171234567"
                            value={phoneForm.data.phone}
                            onChange={(e) =>
                                phoneForm.setData('phone', e.target.value)
                            }
                        />
                        <InputError message={phoneForm.errors.phone} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={
                            phoneForm.processing || !channels.sms.configured
                        }
                    >
                        Send code
                    </Button>
                </form>
                <form
                    className="flex flex-wrap items-start gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        codeForm.post(verifyCode.url(), {
                            preserveScroll: true,
                            onSuccess: () => codeForm.reset(),
                        });
                    }}
                >
                    <div className="space-y-1">
                        <Label htmlFor="code" className="sr-only">
                            Verification code
                        </Label>
                        <Input
                            id="code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            className="w-56"
                            placeholder="6-digit code"
                            value={codeForm.data.code}
                            onChange={(e) =>
                                codeForm.setData('code', e.target.value)
                            }
                        />
                        <InputError message={codeForm.errors.code} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={
                            codeForm.processing ||
                            codeForm.data.code.length !== 6
                        }
                    >
                        Verify
                    </Button>
                </form>
            </section>

            <section className="space-y-4">
                <Heading
                    variant="small"
                    title="Push devices"
                    description="Browser and device support varies. On iPhone and iPad, add Executive Desk to the Home Screen first. Notifications may be delayed or suppressed by the device’s focus or battery settings."
                />
                <div className="flex flex-wrap items-center gap-2">
                    {push.state === 'unsupported' && (
                        <p className="text-sm text-muted-foreground">
                            This browser does not support push notifications.
                        </p>
                    )}
                    {push.state === 'unconfigured' && (
                        <p className="text-sm text-muted-foreground">
                            Push notifications have not been configured on the
                            server (VAPID keys missing).
                        </p>
                    )}
                    {push.state === 'denied' && (
                        <p className="text-sm text-muted-foreground">
                            Notifications are blocked for this site. Allow them
                            in the browser’s site settings, then reload.
                        </p>
                    )}
                    {push.state === 'available' && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={push.busy}
                            onClick={() => void push.enable()}
                        >
                            {push.busy ? <Spinner /> : <BellRing />}
                            Enable push on this device
                        </Button>
                    )}
                    {push.state === 'subscribed' && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={push.busy}
                                onClick={() => void push.disable()}
                            >
                                Turn off on this device
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    router.post(
                                        testPush.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Send a test notification
                            </Button>
                        </>
                    )}
                </div>
                <InputError message={push.error ?? undefined} />
                {devices.length > 0 && (
                    <ul className="divide-y rounded-md border">
                        {devices.map((device) => (
                            <li
                                key={device.id}
                                className="flex items-center gap-3 px-3 py-2"
                            >
                                <Monitor
                                    className="size-4 text-muted-foreground"
                                    aria-hidden
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm">
                                        {device.device_name ?? 'Unknown device'}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Added{' '}
                                        {formatDateTime(
                                            device.created_at,
                                            timezone,
                                        )}
                                        {device.last_used_at &&
                                            ` · last notified ${formatDateTime(device.last_used_at, timezone)}`}
                                    </span>
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove ${device.device_name ?? 'device'}`}
                                    onClick={() =>
                                        router.delete(
                                            destroyDevice.url(device.id),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="space-y-4">
                <Heading variant="small" title="Recent notifications" />
                {recentDeliveries.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing has been sent to you yet.
                    </p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {recentDeliveries.data.map((delivery) => (
                            <li
                                key={delivery.id}
                                className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                            >
                                <span>
                                    {humanize(delivery.notification_type)} ·{' '}
                                    {delivery.channel_label}
                                    <span className="block text-xs text-muted-foreground">
                                        {formatDateTime(
                                            delivery.attempted_at,
                                            timezone,
                                        )}
                                    </span>
                                </span>
                                <StatusBadge status={delivery.status} />
                            </li>
                        ))}
                    </ul>
                )}
                <Link
                    href={history()}
                    className="text-sm underline underline-offset-4"
                >
                    View full delivery history
                </Link>
            </section>
        </>
    );
}

/**
 * The last column of a category row: a priority, or a briefing's delivery time.
 */
function CategorySchedule({
    category,
    settings,
    data,
    onPriority,
    onChange,
}: {
    category: Category;
    settings: CategorySettings;
    data: Preferences;
    onPriority: (priority: Priority) => void;
    onChange: (
        key:
            | 'daily_briefing_time'
            | 'weekly_briefing_day'
            | 'weekly_briefing_time',
        value: string | number,
    ) => void;
}) {
    if (category.schedule === 'daily') {
        return (
            <div className="flex items-center gap-2">
                <span className="text-xs text-muted-foreground">Daily at</span>
                <Input
                    type="time"
                    className="h-8 w-28"
                    aria-label="Daily briefing time"
                    value={data.daily_briefing_time}
                    onChange={(e) =>
                        onChange('daily_briefing_time', e.target.value)
                    }
                />
            </div>
        );
    }

    if (category.schedule === 'weekly') {
        return (
            <div className="flex items-center gap-2">
                <NativeSelect
                    className="h-8 w-28"
                    aria-label="Weekly briefing day"
                    value={data.weekly_briefing_day}
                    onChange={(e) =>
                        onChange('weekly_briefing_day', Number(e.target.value))
                    }
                >
                    {WEEKDAYS.map((day, index) => (
                        <option key={day} value={index + 1}>
                            {day}
                        </option>
                    ))}
                </NativeSelect>
                <Input
                    type="time"
                    className="h-8 w-28"
                    aria-label="Weekly briefing time"
                    value={data.weekly_briefing_time}
                    onChange={(e) =>
                        onChange('weekly_briefing_time', e.target.value)
                    }
                />
            </div>
        );
    }

    return (
        <NativeSelect
            className="h-8 w-32"
            aria-label={`${category.label} priority`}
            value={settings.priority}
            onChange={(e) => onPriority(e.target.value as Priority)}
        >
            {PRIORITIES.map((priority) => (
                <option key={priority.value} value={priority.value}>
                    {priority.label}
                </option>
            ))}
        </NativeSelect>
    );
}

NotificationSettings.layout = {
    breadcrumbs: [{ title: 'Notification preferences', href: edit() }],
};
