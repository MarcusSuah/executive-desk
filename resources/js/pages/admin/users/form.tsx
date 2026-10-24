import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { index, store, update } from '@/routes/admin/users';
import type { Option } from '@/types';

type UserFormProps = {
    user: {
        id: number;
        name: string;
        email: string;
        timezone: string;
        phone: string | null;
        phone_verified: boolean;
        roles: string[];
    } | null;
    roles: Option[];
    timezones: string[];
    defaultTimezone: string;
};

const ROLE_DESCRIPTIONS: Record<string, string> = {
    administrator:
        'Manages accounts, roles, settings, audit logs, and delivery health.',
    assistant:
        'Manages calendars, tasks, presentations, and briefings for executives.',
    executive: 'Has a calendar; views their schedule, tasks, and briefings.',
};

export default function UserForm({
    user,
    roles,
    timezones,
    defaultTimezone,
}: UserFormProps) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        timezone: user?.timezone ?? defaultTimezone,
        phone: user?.phone ?? '',
        roles: user?.roles ?? ['executive'],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (user) {
            form.put(update.url(user.id));
        } else {
            form.post(store.url());
        }
    };

    const toggleRole = (role: string, checked: boolean) =>
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, role]
                : form.data.roles.filter((value) => value !== role),
        );

    const title = user ? `Edit ${user.name}` : 'New user';

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
                        user
                            ? undefined
                            : 'The user receives an email link to set their own password.'
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={index()}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {user ? 'Save changes' : 'Create user'}
                            </Button>
                        </>
                    }
                />

                <Card className="max-w-3xl">
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="name"
                            label="Full name"
                            error={errors.name}
                            required
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                autoComplete="off"
                            />
                        </FormField>
                        <FormField
                            id="email"
                            label="Email"
                            error={errors.email}
                            required
                        >
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                autoComplete="off"
                            />
                        </FormField>
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
                                    form.setData('timezone', e.target.value)
                                }
                            >
                                {timezones.map((zone) => (
                                    <option key={zone} value={zone}>
                                        {zone}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="phone"
                            label="Mobile number"
                            error={errors.phone}
                            hint={
                                user?.phone && !user.phone_verified
                                    ? 'Not verified yet. The user verifies it in their notification settings.'
                                    : 'International format, e.g. +639171234567. Changing it requires re-verification.'
                            }
                        >
                            <Input
                                id="phone"
                                type="tel"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                placeholder="+639171234567"
                            />
                        </FormField>
                        <fieldset className="space-y-3 sm:col-span-2">
                            <legend className="mb-2 text-sm font-medium">
                                Roles
                            </legend>
                            {roles.map((role) => (
                                <label
                                    key={role.value}
                                    className="flex items-start gap-3 rounded-md border p-3"
                                >
                                    <Checkbox
                                        checked={form.data.roles.includes(
                                            role.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleRole(
                                                role.value,
                                                checked === true,
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="block text-sm font-medium">
                                            {role.label}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {ROLE_DESCRIPTIONS[role.value]}
                                        </span>
                                    </span>
                                </label>
                            ))}
                            {(errors.roles || errors['roles.0']) && (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {errors.roles ?? errors['roles.0']}
                                </p>
                            )}
                        </fieldset>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
