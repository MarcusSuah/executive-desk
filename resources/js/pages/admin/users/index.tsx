import { Head, Link, router } from '@inertiajs/react';
import {
    Ban,
    CircleCheck,
    Download,
    Ellipsis,
    KeyRound,
    MailQuestion,
    Pencil,
    Search,
    Shield,
    Trash2,
    UserPlus,
    UserRoundCheck,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { useInitials } from '@/hooks/use-initials';
import { formatDate, formatTime } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import {
    active,
    create,
    destroy,
    edit,
    exportMethod,
    index,
    passwordReset,
} from '@/routes/admin/users';
import type { Option, PaginationLink } from '@/types';

type Status = 'active' | 'pending' | 'suspended';

type AdminUser = {
    id: number;
    name: string;
    job_title: string | null;
    email: string;
    timezone: string;
    is_active: boolean;
    role_labels: string[];
    permissions: string[];
    status: Status;
    scope: { label: string; detail: string };
    last_login_at: string | null;
};

type Filters = { q: string; role: string; status: string; per_page: number };

type UsersIndexProps = {
    users: {
        data: AdminUser[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    stats: {
        total: number;
        pending: number;
        active: number;
        suspended: number;
    };
    filters: Filters;
    roles: Option[];
    perPageOptions: number[];
};

const STATUS_BADGES: Record<Status, { status: string; label: string }> = {
    active: { status: 'active', label: 'Active' },
    pending: { status: 'pending', label: 'Pending invite' },
    suspended: { status: 'failed', label: 'Suspended' },
};

type PendingAction = { kind: 'suspend' | 'remove'; user: AdminUser } | null;

export default function UsersIndex({
    users,
    stats,
    filters,
    roles,
    perPageOptions,
}: UsersIndexProps) {
    const { user: currentUser, timezone } = useAuth();
    const getInitials = useInitials();
    const [search, setSearch] = useState(filters.q);
    const [pending, setPending] = useState<PendingAction>(null);

    const query = (overrides: Partial<Filters> = {}) =>
        Object.fromEntries(
            Object.entries({ ...filters, ...overrides }).filter(
                ([key, value]) =>
                    value !== '' && !(key === 'per_page' && value === 10),
            ),
        );

    const visit = (overrides: Partial<Filters>) =>
        router.get(
            index.url({ query: query(overrides) }),
            {},
            { preserveState: true, preserveScroll: true },
        );

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search.trim() });
    };

    const exportUrl = (format: 'csv' | 'xlsx') =>
        exportMethod.url({ query: { ...query({ per_page: 10 }), format } });

    const confirmPending = (
        close: () => void,
        setBusy: (busy: boolean) => void,
    ) => {
        if (!pending) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => {
                setBusy(false);
                close();
            },
        };

        if (pending.kind === 'remove') {
            router.delete(destroy.url(pending.user.id), options);
        } else {
            router.patch(active.url(pending.user.id), {}, options);
        }
    };

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Users"
                    description="Invite people, assign roles, and control who can sign in."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <UserPlus />
                                New user
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Total users"
                        value={stats.total}
                        icon={Users}
                        tone="info"
                    />
                    <StatCard
                        label="Pending invites"
                        hint="Never signed in"
                        value={stats.pending}
                        icon={MailQuestion}
                        tone="warning"
                    />
                    <StatCard
                        label="Active users"
                        value={stats.active}
                        icon={UserRoundCheck}
                        tone="success"
                    />
                    <StatCard
                        label="Suspended users"
                        value={stats.suspended}
                        icon={Ban}
                        tone={stats.suspended > 0 ? 'danger' : 'neutral'}
                    />
                </div>

                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center">
                        <form
                            onSubmit={submitSearch}
                            className="relative min-w-0 flex-1"
                            role="search"
                        >
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onBlur={() =>
                                    search.trim() !== filters.q &&
                                    visit({ q: search.trim() })
                                }
                                placeholder="Search name, email, or job title"
                                className="pl-9"
                                aria-label="Search users"
                            />
                        </form>
                        <div className="flex flex-wrap items-center gap-2">
                            <NativeSelect
                                className="w-auto"
                                value={filters.per_page}
                                onChange={(e) =>
                                    visit({ per_page: Number(e.target.value) })
                                }
                                aria-label="Rows per page"
                            >
                                {perPageOptions.map((size) => (
                                    <option key={size} value={size}>
                                        {size}
                                    </option>
                                ))}
                            </NativeSelect>
                            <NativeSelect
                                className="w-auto"
                                value={filters.status}
                                onChange={(e) =>
                                    visit({ status: e.target.value })
                                }
                                aria-label="Filter by status"
                            >
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="pending">Pending invite</option>
                                <option value="suspended">Suspended</option>
                            </NativeSelect>
                            <NativeSelect
                                className="w-auto"
                                value={filters.role}
                                onChange={(e) =>
                                    visit({ role: e.target.value })
                                }
                                aria-label="Filter by role"
                            >
                                <option value="">All roles</option>
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        disabled={users.total === 0}
                                    >
                                        <Download />
                                        Export
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem asChild>
                                        <a href={exportUrl('csv')}>CSV</a>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <a href={exportUrl('xlsx')}>
                                            Excel (.xlsx)
                                        </a>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    {users.data.length === 0 ? (
                        <div className="p-6">
                            <EmptyState
                                icon={Users}
                                title="No users match these filters"
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[960px] text-sm">
                                <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2.5">User</th>
                                        <th className="px-4 py-2.5">Email</th>
                                        <th className="px-4 py-2.5">Role</th>
                                        <th className="px-4 py-2.5">Scope</th>
                                        <th className="px-4 py-2.5">Status</th>
                                        <th className="px-4 py-2.5">
                                            Last login
                                        </th>
                                        <th className="px-4 py-2.5 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {users.data.map((user) => {
                                        const isSelf =
                                            user.id === currentUser.id;
                                        const badge =
                                            STATUS_BADGES[user.status];

                                        return (
                                            <tr
                                                key={user.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3">
                                                    <span className="flex items-center gap-3">
                                                        <span
                                                            aria-hidden
                                                            className={cn(
                                                                'flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                                                user.is_active
                                                                    ? 'bg-primary/10 text-primary'
                                                                    : 'bg-muted text-muted-foreground',
                                                            )}
                                                        >
                                                            {getInitials(
                                                                user.name,
                                                            )}
                                                        </span>
                                                        <span className="min-w-0">
                                                            <Link
                                                                href={edit(
                                                                    user.id,
                                                                )}
                                                                className="block font-medium hover:underline"
                                                            >
                                                                {user.name}
                                                                {isSelf && (
                                                                    <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                                        (you)
                                                                    </span>
                                                                )}
                                                            </Link>
                                                            <span className="block truncate text-xs text-muted-foreground">
                                                                {user.job_title ??
                                                                    'No job title'}
                                                            </span>
                                                        </span>
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {user.email}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {user.role_labels.length ===
                                                    0
                                                        ? '—'
                                                        : user.role_labels.map(
                                                              (label) => (
                                                                  <span
                                                                      key={
                                                                          label
                                                                      }
                                                                      className="flex items-center gap-1.5 whitespace-nowrap"
                                                                  >
                                                                      <Shield
                                                                          className="size-3.5 text-muted-foreground"
                                                                          aria-hidden
                                                                      />
                                                                      {label}
                                                                  </span>
                                                              ),
                                                          )}
                                                    {user.permissions.length >
                                                        0 && (
                                                        <span className="mt-0.5 block text-xs text-muted-foreground">
                                                            +{' '}
                                                            {
                                                                user.permissions
                                                                    .length
                                                            }{' '}
                                                            individual
                                                            permission
                                                            {user.permissions
                                                                .length === 1
                                                                ? ''
                                                                : 's'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="block">
                                                        {user.scope.label}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {user.scope.detail}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        status={badge.status}
                                                        label={badge.label}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {user.last_login_at ? (
                                                        <>
                                                            <span className="block">
                                                                {formatDate(
                                                                    user.last_login_at,
                                                                    timezone,
                                                                )}
                                                            </span>
                                                            <span className="block text-xs text-muted-foreground">
                                                                {formatTime(
                                                                    user.last_login_at,
                                                                    timezone,
                                                                )}
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Never
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Actions for ${user.name}`}
                                                            >
                                                                <Ellipsis />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={edit(
                                                                        user.id,
                                                                    )}
                                                                >
                                                                    <Pencil />
                                                                    Edit user
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onSelect={() =>
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
                                                                <KeyRound />
                                                                Send password
                                                                reset
                                                            </DropdownMenuItem>
                                                            {!isSelf && (
                                                                <>
                                                                    <DropdownMenuItem
                                                                        onSelect={() =>
                                                                            setPending(
                                                                                {
                                                                                    kind: 'suspend',
                                                                                    user,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        {user.is_active ? (
                                                                            <Ban />
                                                                        ) : (
                                                                            <CircleCheck />
                                                                        )}
                                                                        {user.is_active
                                                                            ? 'Suspend user'
                                                                            : 'Reactivate user'}
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        variant="destructive"
                                                                        onSelect={() =>
                                                                            setPending(
                                                                                {
                                                                                    kind: 'remove',
                                                                                    user,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 />
                                                                        Remove
                                                                        user
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {users.total > 0 && (
                        <nav
                            className="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 sm:flex-row"
                            aria-label="Pagination"
                        >
                            <p className="text-sm text-muted-foreground">
                                Showing {users.from} to {users.to} of{' '}
                                {users.total} users
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {users.links.map((link, position) => {
                                    const label = link.label
                                        .replace('&laquo;', '‹')
                                        .replace('&raquo;', '›');

                                    return link.url ? (
                                        <Link
                                            key={position}
                                            href={link.url}
                                            preserveScroll
                                            aria-current={
                                                link.active ? 'page' : undefined
                                            }
                                            className={cn(
                                                'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm hover:bg-accent',
                                                link.active &&
                                                    'border-primary bg-primary text-primary-foreground hover:bg-primary/90',
                                            )}
                                        >
                                            {label}
                                        </Link>
                                    ) : (
                                        <span
                                            key={position}
                                            className="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-muted-foreground"
                                        >
                                            {label}
                                        </span>
                                    );
                                })}
                            </div>
                        </nav>
                    )}
                </section>
            </div>

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
                title={
                    pending?.kind === 'remove'
                        ? `Remove ${pending.user.name}?`
                        : pending?.user.is_active
                          ? `Suspend ${pending.user.name}?`
                          : `Reactivate ${pending?.user.name ?? ''}?`
                }
                description={
                    pending?.kind === 'remove'
                        ? 'The account is deleted permanently and cannot be restored. Tasks assigned to them become unassigned. People who own appointments or presentations must be suspended instead.'
                        : pending?.user.is_active
                          ? 'They are signed out immediately and can no longer sign in. Their records are kept.'
                          : 'They will be able to sign in again.'
                }
                confirmLabel={
                    pending?.kind === 'remove'
                        ? 'Remove user'
                        : pending?.user.is_active
                          ? 'Suspend'
                          : 'Reactivate'
                }
                destructive={
                    pending?.kind === 'remove' || pending?.user.is_active
                }
                onConfirm={confirmPending}
            />
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
