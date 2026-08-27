import { Link } from '@inertiajs/react';
import {
    BookOpen,
    BriefcaseBusiness,
    FolderGit2,
    KeyRound,
    LayoutGrid,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import { index as designationsIndex } from '@/routes/admin/designations';
import { index as permissionsIndex } from '@/routes/admin/permissions';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    // Hiding a link is convenience, not authorization — the routes are gated by
    // the `permission:` middleware and the module's policies.
    const canViewUsers = useCan('admin.users.view');
    const canViewRoles = useCan('admin.roles.view');
    const canViewPermissions = useCan('admin.permissions.view');
    const canViewDesignations = useCan('admin.designations.view');

    // Admin surfaces live in their own group, not alongside Platform.
    const adminNavItems: NavItem[] = [
        ...(canViewUsers
            ? [{ title: 'Users', href: usersIndex(), icon: Users }]
            : []),
        ...(canViewDesignations
            ? [
                  {
                      title: 'Designations',
                      href: designationsIndex(),
                      icon: BriefcaseBusiness,
                  },
              ]
            : []),
        ...(canViewRoles
            ? [{ title: 'Roles', href: rolesIndex(), icon: ShieldCheck }]
            : []),
        ...(canViewPermissions
            ? [
                  {
                      title: 'Permissions',
                      href: permissionsIndex(),
                      icon: KeyRound,
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

                {adminNavItems.length > 0 && (
                    <NavMain items={adminNavItems} label="Admin" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
