import { Head, Link, useForm } from '@inertiajs/react';
import { Check, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { groupPermissions } from '@/lib/permissions';
import type { PermissionInfo } from '@/lib/permissions';
import { index, store, update } from '@/routes/admin/users';

type RoleOption = {
    value: string;
    label: string;
    description: string | null;
    permissions: string[];
};

type UserFormProps = {
    user: {
        id: number;
        name: string;
        email: string;
        timezone: string;
        phone: string | null;
        phone_verified: boolean;
        roles: string[];
        permissions: string[];
    } | null;
    roles: RoleOption[];
    permissions: PermissionInfo[];
    canManagePermissions: boolean;
    timezones: string[];
    defaultTimezone: string;
};

export default function UserForm({
    user,
    roles,
    permissions,
    canManagePermissions,
    timezones,
    defaultTimezone,
}: UserFormProps) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        timezone: user?.timezone ?? defaultTimezone,
        phone: user?.phone ?? '',
        roles: user?.roles ?? ['executive'],
        permissions: user?.permissions ?? [],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Only people who manage permissions may send individual permissions.
        form.transform((data) =>
            canManagePermissions ? data : { ...data, permissions: undefined },
        );

        if (user) {
            form.put(update.url(user.id));
        } else {
            form.post(store.url());
        }
    };

    const toggle = (
        field: 'roles' | 'permissions',
        value: string,
        checked: boolean,
    ) =>
        form.setData(
            field,
            checked
                ? [...form.data[field], value]
                : form.data[field].filter((item) => item !== value),
        );

    // Which selected roles grant each permission, for the effective access preview.
    const grantedBy = new Map<string, string[]>();
    for (const role of roles.filter((option) =>
        form.data.roles.includes(option.value),
    )) {
        for (const permission of role.permissions) {
            grantedBy.set(permission, [
                ...(grantedBy.get(permission) ?? []),
                role.label,
            ]);
        }
    }

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

                <div className="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Account
                                </CardTitle>
                            </CardHeader>
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
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
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
                                            form.setData(
                                                'timezone',
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
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="+639171234567"
                                    />
                                </FormField>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Roles
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Choose one or more. The person gets
                                    everything each role allows.
                                </p>
                            </CardHeader>
                            <CardContent className="grid gap-2 sm:grid-cols-2">
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
                                                toggle(
                                                    'roles',
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
                                            {role.description && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {role.description}
                                                </span>
                                            )}
                                        </span>
                                    </label>
                                ))}
                                <InputError
                                    className="sm:col-span-2"
                                    message={errors.roles ?? errors['roles.0']}
                                />
                            </CardContent>
                        </Card>

                        {canManagePermissions && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Individual permissions
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Extra permissions for this person only,
                                        on top of their roles. Permissions
                                        already given by a selected role are
                                        shown as included.
                                    </p>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {groupPermissions(permissions).map(
                                        ([group, items]) => (
                                            <div
                                                key={group}
                                                className="space-y-2"
                                            >
                                                <p className="text-xs font-semibold text-muted-foreground uppercase">
                                                    {group}
                                                </p>
                                                <div className="grid gap-2 sm:grid-cols-2">
                                                    {items.map((permission) => {
                                                        const fromRoles =
                                                            grantedBy.get(
                                                                permission.value,
                                                            );

                                                        return (
                                                            <label
                                                                key={
                                                                    permission.value
                                                                }
                                                                className="flex items-start gap-3 rounded-md border p-2.5"
                                                                title={
                                                                    permission.description
                                                                }
                                                            >
                                                                <Checkbox
                                                                    checked={form.data.permissions.includes(
                                                                        permission.value,
                                                                    )}
                                                                    onCheckedChange={(
                                                                        checked,
                                                                    ) =>
                                                                        toggle(
                                                                            'permissions',
                                                                            permission.value,
                                                                            checked ===
                                                                                true,
                                                                        )
                                                                    }
                                                                    className="mt-0.5"
                                                                />
                                                                <span>
                                                                    <span className="block text-sm">
                                                                        {
                                                                            permission.label
                                                                        }
                                                                    </span>
                                                                    {fromRoles && (
                                                                        <span className="block text-xs text-muted-foreground">
                                                                            Included
                                                                            in{' '}
                                                                            {fromRoles.join(
                                                                                ', ',
                                                                            )}
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        ),
                                    )}
                                    <InputError
                                        message={
                                            errors.permissions ??
                                            errors['permissions.0']
                                        }
                                    />
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="h-fit xl:sticky xl:top-4">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ShieldCheck className="size-4" aria-hidden />
                                What this person can do
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {permissions.map((permission) => {
                                    const fromRoles =
                                        grantedBy.get(permission.value) ?? [];
                                    const individually =
                                        form.data.permissions.includes(
                                            permission.value,
                                        );
                                    const allowed =
                                        fromRoles.length > 0 || individually;

                                    return (
                                        <li
                                            key={permission.value}
                                            className="flex items-start gap-2 text-sm"
                                        >
                                            <span
                                                className={
                                                    allowed
                                                        ? 'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                        : 'mt-0.5 size-4 shrink-0 rounded-full border'
                                                }
                                                aria-hidden
                                            >
                                                {allowed && (
                                                    <Check className="size-3" />
                                                )}
                                            </span>
                                            <span
                                                className={
                                                    allowed
                                                        ? ''
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {permission.label}
                                                {allowed && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {[
                                                            ...fromRoles,
                                                            ...(individually
                                                                ? [
                                                                      'individually',
                                                                  ]
                                                                : []),
                                                        ].join(', ')}
                                                    </span>
                                                )}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                            <p className="mt-4 text-xs text-muted-foreground">
                                Everyone can always see and manage their own
                                appointments, tasks, presentations, briefings,
                                and settings.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
