import { Head, Link, router } from '@inertiajs/react';
import { KeyRound, Pencil, Search, UserPlus, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import {
    active,
    create,
    edit,
    index,
    passwordReset,
} from '@/routes/admin/users';
import type { Option, PaginationLink } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    timezone: string;
    phone: string | null;
    phone_verified: boolean;
    is_active: boolean;
    roles: string[];
    role_labels: string[];
    permissions: string[];
    last_login_at: string | null;
    created_at: string | null;
};

type Filters = { q: string; role: string; status: string };

type UsersIndexProps = {
    users: {
        data: AdminUser[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: Filters;
    roles: Option[];
};

export default function UsersIndex({ users, filters, roles }: UsersIndexProps) {
    const { user: currentUser, timezone } = useAuth();
    const [search, setSearch] = useState(filters.q);

    const visit = (overrides: Partial<Filters>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...overrides }).filter(
                ([, value]) => value !== '',
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

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Users"
                    description="Create accounts, assign roles, and deactivate access."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <UserPlus />
                                New user
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={submitSearch} className="relative">
                        <Search
                            className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                            aria-hidden
                        />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search name or email"
                            className="w-64 pl-8"
                            aria-label="Search users"
                        />
                    </form>
                    <NativeSelect
                        className="w-auto"
                        value={filters.role}
                        onChange={(e) => visit({ role: e.target.value })}
                        aria-label="Filter by role"
                    >
                        <option value="">All roles</option>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-auto"
                        value={filters.status}
                        onChange={(e) => visit({ status: e.target.value })}
                        aria-label="Filter by status"
                    >
                        <option value="">Any status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Deactivated</option>
                    </NativeSelect>
                </div>

                {users.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No users match these filters"
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        User
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Roles
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Time zone
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Last sign-in
                                    </th>
                                    <th className="px-3 py-2">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.map((user) => (
                                    <tr key={user.id}>
                                        <td className="px-3 py-2">
                                            <span className="block font-medium">
                                                {user.name}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {user.email}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2">
                                            <span className="flex flex-wrap gap-1">
                                                {user.role_labels.length === 0
                                                    ? '—'
                                                    : user.role_labels.map(
                                                          (label) => (
                                                              <span
                                                                  key={label}
                                                                  className="rounded-md bg-muted px-1.5 py-0.5 text-xs"
                                                              >
                                                                  {label}
                                                              </span>
                                                          ),
                                                      )}
                                            </span>
                                            {user.permissions.length > 0 && (
                                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                                    + {user.permissions.length}{' '}
                                                    individual permission
                                                    {user.permissions.length ===
                                                    1
                                                        ? ''
                                                        : 's'}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {user.timezone}
                                        </td>
                                        <td className="px-3 py-2">
                                            <StatusBadge
                                                status={
                                                    user.is_active
                                                        ? 'active'
                                                        : 'inactive'
                                                }
                                                label={
                                                    user.is_active
                                                        ? 'Active'
                                                        : 'Deactivated'
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {user.last_login_at
                                                ? formatDateTime(
                                                      user.last_login_at,
                                                      timezone,
                                                  )
                                                : 'Never'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                    aria-label={`Edit ${user.name}`}
                                                >
                                                    <Link href={edit(user.id)}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Send password reset link to ${user.name}`}
                                                    onClick={() =>
                                                        router.post(
                                                            passwordReset.url(
                                                                user.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <KeyRound className="size-4" />
                                                </Button>
                                                {user.id !== currentUser.id && (
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                            >
                                                                {user.is_active
                                                                    ? 'Deactivate'
                                                                    : 'Reactivate'}
                                                            </Button>
                                                        }
                                                        title={
                                                            user.is_active
                                                                ? `Deactivate ${user.name}?`
                                                                : `Reactivate ${user.name}?`
                                                        }
                                                        description={
                                                            user.is_active
                                                                ? 'They are signed out immediately and can no longer sign in. Their records are kept.'
                                                                : 'They will be able to sign in again.'
                                                        }
                                                        confirmLabel={
                                                            user.is_active
                                                                ? 'Deactivate'
                                                                : 'Reactivate'
                                                        }
                                                        destructive={
                                                            user.is_active
                                                        }
                                                        onConfirm={(
                                                            close,
                                                            setBusy,
                                                        ) => {
                                                            setBusy(true);
                                                            router.patch(
                                                                active.url(
                                                                    user.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                    onFinish:
                                                                        () => {
                                                                            setBusy(
                                                                                false,
                                                                            );
                                                                            close();
                                                                        },
                                                                },
                                                            );
                                                        }}
                                                    />
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {users.links.length > 3 && (
                    <nav
                        className="flex flex-wrap gap-1"
                        aria-label="Pagination"
                    >
                        {users.links.map((link, position) =>
                            link.url ? (
                                <Link
                                    key={position}
                                    href={link.url}
                                    preserveScroll
                                    className={`inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'}`}
                                >
                                    {link.label
                                        .replace('&laquo;', '‹')
                                        .replace('&raquo;', '›')}
                                </Link>
                            ) : null,
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
