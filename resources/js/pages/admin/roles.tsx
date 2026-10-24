import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    Lock,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { groupPermissions } from '@/lib/permissions';
import type { PermissionInfo } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { destroy, index, store, update } from '@/routes/admin/roles';

type RoleRow = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    built_in: boolean;
    permissions: string[];
    users_count: number;
};

type RolesProps = {
    roles: RoleRow[];
    permissions: PermissionInfo[];
    locked: Record<string, string[]>;
};

export default function Roles({ roles, permissions, locked }: RolesProps) {
    const [editing, setEditing] = useState<RoleRow | 'new' | null>(null);
    const groups = groupPermissions(permissions);

    return (
        <>
            <Head title="Roles & permissions" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Roles & permissions"
                    description="A person can hold several roles. Their access is everything their roles grant, plus any permissions given to them individually on their account."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus />
                            New role
                        </Button>
                    }
                />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/40">
                            <tr>
                                <th className="sticky left-0 z-10 w-80 bg-muted/40 px-4 py-3 text-left align-bottom text-xs font-medium text-muted-foreground uppercase">
                                    Permission
                                </th>
                                {roles.map((role) => (
                                    <th
                                        key={role.id}
                                        className="min-w-36 px-3 py-3 text-center align-bottom"
                                    >
                                        <div className="flex flex-col items-center gap-1">
                                            <span className="font-semibold">
                                                {role.label}
                                            </span>
                                            <span className="flex items-center gap-1 text-xs font-normal text-muted-foreground">
                                                <Users
                                                    className="size-3"
                                                    aria-hidden
                                                />
                                                {role.users_count}{' '}
                                                {role.users_count === 1
                                                    ? 'person'
                                                    : 'people'}
                                            </span>
                                            {role.built_in && (
                                                <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground uppercase">
                                                    Built-in
                                                </span>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7"
                                                onClick={() => setEditing(role)}
                                            >
                                                <Pencil className="size-3.5" />
                                                Edit
                                            </Button>
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {groups.map(([group, items]) => (
                                <PermissionGroupRows
                                    key={group}
                                    group={group}
                                    items={items}
                                    roles={roles}
                                    locked={locked}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {editing !== null && (
                <RoleDialog
                    role={editing === 'new' ? null : editing}
                    permissions={permissions}
                    locked={
                        editing !== 'new' ? (locked[editing.name] ?? []) : []
                    }
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function PermissionGroupRows({
    group,
    items,
    roles,
    locked,
}: {
    group: string;
    items: PermissionInfo[];
    roles: RoleRow[];
    locked: Record<string, string[]>;
}) {
    return (
        <>
            <tr className="border-t bg-muted/20">
                <th
                    colSpan={roles.length + 1}
                    className="sticky left-0 px-4 py-2 text-left text-xs font-semibold text-muted-foreground uppercase"
                >
                    {group}
                </th>
            </tr>
            {items.map((permission) => (
                <tr key={permission.value} className="border-t">
                    <th
                        scope="row"
                        className="sticky left-0 z-10 bg-background px-4 py-2.5 text-left font-normal"
                    >
                        <span className="block font-medium">
                            {permission.label}
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            {permission.description}
                        </span>
                    </th>
                    {roles.map((role) => {
                        const granted = role.permissions.includes(
                            permission.value,
                        );
                        const isLocked = (locked[role.name] ?? []).includes(
                            permission.value,
                        );

                        return (
                            <td
                                key={role.id}
                                className="px-3 py-2.5 text-center"
                            >
                                {granted ? (
                                    <span
                                        className="inline-flex size-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                        title={
                                            isLocked
                                                ? 'Always granted to this role'
                                                : 'Granted'
                                        }
                                    >
                                        {isLocked ? (
                                            <Lock className="size-3.5" />
                                        ) : (
                                            <Check className="size-3.5" />
                                        )}
                                        <span className="sr-only">
                                            {isLocked
                                                ? 'Always granted'
                                                : 'Granted'}
                                        </span>
                                    </span>
                                ) : (
                                    <span
                                        className="text-muted-foreground/50"
                                        aria-label="Not granted"
                                    >
                                        —
                                    </span>
                                )}
                            </td>
                        );
                    })}
                </tr>
            ))}
        </>
    );
}

function RoleDialog({
    role,
    permissions,
    locked,
    onClose,
}: {
    role: RoleRow | null;
    permissions: PermissionInfo[];
    locked: string[];
    onClose: () => void;
}) {
    const form = useForm({
        name: role?.built_in ? undefined : (role?.name ?? ''),
        description: role?.built_in ? undefined : (role?.description ?? ''),
        permissions: role?.permissions ?? [],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const groups = groupPermissions(permissions);

    const toggle = (value: string, checked: boolean) =>
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, value]
                : form.data.permissions.filter(
                      (permission) => permission !== value,
                  ),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (role) {
            form.put(update.url(role.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-1">
                        <DialogTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-5" aria-hidden />
                            {role ? `Edit ${role.label}` : 'New role'}
                        </DialogTitle>
                        <DialogDescription>
                            {role?.built_in
                                ? `${role.description ?? ''} Built-in roles can’t be renamed or deleted, but you can change their permissions.`
                                : 'Give the role a name and choose what it allows. Assign it to people on their account page.'}
                        </DialogDescription>
                    </div>

                    {!role?.built_in && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="role-name"
                                label="Name"
                                error={errors.name}
                                required
                            >
                                <Input
                                    id="role-name"
                                    value={form.data.name ?? ''}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    maxLength={50}
                                    placeholder="e.g. Chief of Staff"
                                    autoFocus
                                />
                            </FormField>
                            <FormField
                                id="role-description"
                                label="Description"
                                error={errors.description}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="role-description"
                                    value={form.data.description ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={500}
                                    rows={2}
                                />
                            </FormField>
                        </div>
                    )}

                    <fieldset className="space-y-4">
                        <legend className="text-sm font-medium">
                            Permissions
                        </legend>
                        {groups.map(([group, items]) => (
                            <div key={group} className="space-y-2">
                                <p className="text-xs font-semibold text-muted-foreground uppercase">
                                    {group}
                                </p>
                                {items.map((permission) => {
                                    const isLocked = locked.includes(
                                        permission.value,
                                    );
                                    const checked =
                                        form.data.permissions.includes(
                                            permission.value,
                                        );

                                    return (
                                        <label
                                            key={permission.value}
                                            className={cn(
                                                'flex items-start gap-3 rounded-md border p-2.5',
                                                {
                                                    'bg-muted/40': isLocked,
                                                },
                                            )}
                                        >
                                            <Checkbox
                                                checked={checked}
                                                disabled={isLocked}
                                                onCheckedChange={(value) =>
                                                    toggle(
                                                        permission.value,
                                                        value === true,
                                                    )
                                                }
                                                className="mt-0.5"
                                            />
                                            <span>
                                                <span className="flex items-center gap-1.5 text-sm font-medium">
                                                    {permission.label}
                                                    {isLocked && (
                                                        <Lock
                                                            className="size-3 text-muted-foreground"
                                                            aria-label="Required"
                                                        />
                                                    )}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {isLocked
                                                        ? 'Always kept on this role so nobody is locked out of user and role management.'
                                                        : permission.description}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        ))}
                        <InputError
                            message={
                                errors.permissions ?? errors['permissions.0']
                            }
                        />
                    </fieldset>

                    <DialogFooter className="gap-2 sm:justify-between">
                        <div>
                            {role && !role.built_in && (
                                <ConfirmDialog
                                    trigger={
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="text-destructive hover:text-destructive"
                                        >
                                            <Trash2 />
                                            Delete role
                                        </Button>
                                    }
                                    title={`Delete “${role.label}”?`}
                                    description={
                                        role.users_count > 0
                                            ? `${role.users_count} ${role.users_count === 1 ? 'person loses' : 'people lose'} this role and the access it gives. Their other roles are not affected.`
                                            : 'Nobody has this role.'
                                    }
                                    confirmLabel="Delete role"
                                    destructive
                                    onConfirm={(close, setBusy) => {
                                        setBusy(true);
                                        router.delete(destroy.url(role.id), {
                                            preserveScroll: true,
                                            onFinish: () => {
                                                setBusy(false);
                                                close();
                                                onClose();
                                            },
                                        });
                                    }}
                                />
                            )}
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {role ? 'Save role' : 'Create role'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

Roles.layout = {
    breadcrumbs: [{ title: 'Roles & permissions', href: index() }],
};
