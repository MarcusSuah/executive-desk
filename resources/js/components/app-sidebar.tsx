import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    CheckSquare,
    LayoutGrid,
    Presentation,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAuth } from '@/hooks/use-auth';
import { calendar, dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/admin/users';
import { index as presentationsIndex } from '@/routes/presentations';
import { index as tasksIndex } from '@/routes/tasks';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { can } = useAuth();

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Calendar', href: calendar(), icon: CalendarDays },
        { title: 'Tasks', href: tasksIndex(), icon: CheckSquare },
        {
            title: 'Presentations',
            href: presentationsIndex(),
            icon: Presentation,
        },
    ];

    const adminNavItems: NavItem[] = can('users.manage')
        ? [{ title: 'Users', href: usersIndex(), icon: Users }]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <NavMain items={adminNavItems} label="Administration" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
