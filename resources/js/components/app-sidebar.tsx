import { Link } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    CalendarDays,
    CheckSquare,
    FileText,
    LayoutGrid,
    Presentation,
    ScrollText,
    ShieldCheck,
    Settings2,
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
import { index as auditLogsIndex } from '@/routes/admin/audit-logs';
import { index as rolesIndex } from '@/routes/admin/roles';
import { edit as adminSettings } from '@/routes/admin/settings';
import { index as usersIndex } from '@/routes/admin/users';
import { index as briefingsIndex } from '@/routes/briefings';
import { history as deliveryHistory } from '@/routes/notifications';
import { index as presentationsIndex } from '@/routes/presentations';
import { index as reportsIndex } from '@/routes/reports';
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
        { title: 'Briefings', href: briefingsIndex(), icon: FileText },
        ...(can('reports.view')
            ? [{ title: 'Reports', href: reportsIndex(), icon: BarChart3 }]
            : []),
        { title: 'Delivery history', href: deliveryHistory(), icon: Bell },
    ];

    const adminNavItems: NavItem[] = [
        ...(can('users.manage')
            ? [{ title: 'Users', href: usersIndex(), icon: Users }]
            : []),
        ...(can('roles.manage')
            ? [
                  {
                      title: 'Roles & permissions',
                      href: rolesIndex(),
                      icon: ShieldCheck,
                  },
              ]
            : []),
        ...(can('settings.manage')
            ? [{ title: 'Settings', href: adminSettings(), icon: Settings2 }]
            : []),
        ...(can('audit.view')
            ? [
                  {
                      title: 'Activity log',
                      href: auditLogsIndex(),
                      icon: ScrollText,
                  },
              ]
            : []),
    ];

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
