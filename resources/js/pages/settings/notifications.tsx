import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    Mail,
    MessageSquare,
    Monitor,
    Smartphone,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useAuth } from '@/hooks/use-auth';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import { formatDateTime } from '@/lib/datetime';
import { humanize } from '@/lib/labels';
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

type Preferences = {
    email_enabled: boolean;
    sms_enabled: boolean;
    push_enabled: boolean;
    event_reminder_minutes: number[];
    task_reminder_minutes: number[];
    presentation_reminder_minutes: number[];
    schedule_change_notifications: boolean;
    daily_briefing_enabled: boolean;
    daily_briefing_time: string;
    daily_briefing_channels: string[];
    weekly_briefing_enabled: boolean;
    weekly_briefing_day: number;
    weekly_briefing_time: string;
    weekly_briefing_channels: string[];
};

type ChannelStatus = { configured: boolean; detail: string | null };

type NotificationSettingsProps = {
    preferences: Preferences;
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

const CHANNELS = [
    { value: 'mail', label: 'Email' },
    { value: 'sms', label: 'SMS' },
    { value: 'push', label: 'Push' },
];

type MinutesKey =
    | 'event_reminder_minutes'
    | 'task_reminder_minutes'
    | 'presentation_reminder_minutes';

export default function NotificationSettings({
    preferences,
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

    const submit = (event: FormEvent) => {
        event.preventDefault();
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

    const toggleChannel = (
        key: 'daily_briefing_channels' | 'weekly_briefing_channels',
        channel: string,
        checked: boolean,
    ) =>
        form.setData(
            key,
            checked
                ? [...form.data[key], channel]
                : form.data[key].filter((value) => value !== channel),
        );

    const minutesGroups: { key: MinutesKey; label: string }[] = [
        { key: 'event_reminder_minutes', label: 'Meetings and appointments' },
        { key: 'task_reminder_minutes', label: 'Task deadlines' },
        {
            key: 'presentation_reminder_minutes',
            label: 'Presentation deadlines',
        },
    ];

    return (
        <>
            <Head title="Notification settings" />
            <h1 className="sr-only">Notification settings</h1>

            <form onSubmit={submit} className="space-y-10">
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Channels"
                        description="Choose how reminders and briefings reach you."
                    />

                    <ChannelRow
                        icon={Mail}
                        label="Email"
                        detail={contact.email}
                        status={channels.mail}
                        checked={form.data.email_enabled}
                        onChange={(checked) =>
                            form.setData('email_enabled', checked)
                        }
                        error={errors.email_enabled}
                    />
                    <ChannelRow
                        icon={MessageSquare}
                        label="SMS"
                        detail={
                            contact.phone_verified
                                ? contact.phone
                                : 'Verify a mobile number below to enable SMS.'
                        }
                        status={channels.sms}
                        checked={form.data.sms_enabled}
                        disabled={
                            !contact.phone_verified || !channels.sms.configured
                        }
                        onChange={(checked) =>
                            form.setData('sms_enabled', checked)
                        }
                        error={errors.sms_enabled}
                    />
                    <ChannelRow
                        icon={Smartphone}
                        label="Push"
                        detail={
                            devices.length > 0
                                ? `${devices.length} registered device(s)`
                                : 'Enable push on a device below.'
                        }
                        status={channels.push}
                        checked={form.data.push_enabled}
                        disabled={devices.length === 0}
                        onChange={(checked) =>
                            form.setData('push_enabled', checked)
                        }
                        error={errors.push_enabled}
                    />

                    <label className="flex items-center gap-3 text-sm">
                        <Checkbox
                            checked={form.data.schedule_change_notifications}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'schedule_change_notifications',
                                    checked === true,
                                )
                            }
                        />
                        Tell me when someone reschedules, moves, or cancels one
                        of my appointments
                    </label>
                </section>

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

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Briefings"
                        description={`Delivered at the chosen time in your time zone (${timezone}).`}
                    />
                    <div className="space-y-3 rounded-lg border p-4">
                        <label className="flex items-center gap-3 text-sm font-medium">
                            <Checkbox
                                checked={form.data.daily_briefing_enabled}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'daily_briefing_enabled',
                                        checked === true,
                                    )
                                }
                            />
                            Daily briefing
                        </label>
                        {form.data.daily_briefing_enabled && (
                            <div className="flex flex-wrap items-center gap-4 pl-7">
                                <Input
                                    type="time"
                                    className="w-32"
                                    aria-label="Daily briefing time"
                                    value={form.data.daily_briefing_time}
                                    onChange={(e) =>
                                        form.setData(
                                            'daily_briefing_time',
                                            e.target.value,
                                        )
                                    }
                                />
                                {CHANNELS.map((channel) => (
                                    <label
                                        key={channel.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.daily_briefing_channels.includes(
                                                channel.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleChannel(
                                                    'daily_briefing_channels',
                                                    channel.value,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {channel.label}
                                    </label>
                                ))}
                            </div>
                        )}
                        <InputError
                            message={
                                errors.daily_briefing_time ??
                                errors.daily_briefing_channels
                            }
                        />
                    </div>
                    <div className="space-y-3 rounded-lg border p-4">
                        <label className="flex items-center gap-3 text-sm font-medium">
                            <Checkbox
                                checked={form.data.weekly_briefing_enabled}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'weekly_briefing_enabled',
                                        checked === true,
                                    )
                                }
                            />
                            Weekly briefing
                        </label>
                        {form.data.weekly_briefing_enabled && (
                            <div className="flex flex-wrap items-center gap-4 pl-7">
                                <NativeSelect
                                    className="w-36"
                                    aria-label="Weekly briefing day"
                                    value={form.data.weekly_briefing_day}
                                    onChange={(e) =>
                                        form.setData(
                                            'weekly_briefing_day',
                                            Number(e.target.value),
                                        )
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
                                    className="w-32"
                                    aria-label="Weekly briefing time"
                                    value={form.data.weekly_briefing_time}
                                    onChange={(e) =>
                                        form.setData(
                                            'weekly_briefing_time',
                                            e.target.value,
                                        )
                                    }
                                />
                                {CHANNELS.map((channel) => (
                                    <label
                                        key={channel.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.weekly_briefing_channels.includes(
                                                channel.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleChannel(
                                                    'weekly_briefing_channels',
                                                    channel.value,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {channel.label}
                                    </label>
                                ))}
                            </div>
                        )}
                        <InputError
                            message={
                                errors.weekly_briefing_time ??
                                errors.weekly_briefing_channels
                            }
                        />
                    </div>
                </section>

                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save notification settings
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

function ChannelRow({
    icon: Icon,
    label,
    detail,
    status,
    checked,
    disabled = false,
    onChange,
    error,
}: {
    icon: typeof Mail;
    label: string;
    detail: string | null;
    status: ChannelStatus;
    checked: boolean;
    disabled?: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
}) {
    const id = `channel-${label.toLowerCase()}`;

    return (
        <div className="space-y-1">
            <div className="flex items-center gap-3 rounded-lg border p-3">
                <Icon className="size-5 text-muted-foreground" aria-hidden />
                <div className="min-w-0 flex-1">
                    <Label htmlFor={id} className="flex items-center gap-2">
                        {label}
                        {!status.configured && (
                            <StatusBadge
                                status="unavailable"
                                label="Not configured"
                            />
                        )}
                    </Label>
                    {detail && (
                        <p className="truncate text-xs text-muted-foreground">
                            {detail}
                        </p>
                    )}
                </div>
                <Checkbox
                    id={id}
                    checked={checked}
                    disabled={disabled && !checked}
                    onCheckedChange={(value) => onChange(value === true)}
                />
            </div>
            <InputError message={error} />
        </div>
    );
}

NotificationSettings.layout = {
    breadcrumbs: [{ title: 'Notification settings', href: edit() }],
};
