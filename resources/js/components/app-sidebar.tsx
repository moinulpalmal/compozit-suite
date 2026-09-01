import { Link } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CalendarClock,
    CalendarCog,
    KeyRound,
    LayoutGrid,
    Palette,
    ScrollText,
    ShieldCheck,
    Store,
    Table2,
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
import { useCan } from '@/hooks/use-can';
import { useNavGroups } from '@/hooks/use-nav-groups';
import { dashboard } from '@/routes';
import { index as buyersIndex } from '@/routes/admin/buyers';
import { index as designationsIndex } from '@/routes/admin/designations';
import { index as permissionsIndex } from '@/routes/admin/permissions';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import { index as bqsIndex } from '@/routes/merchandising/bqs';
import { index as purchaseOrdersIndex } from '@/routes/merchandising/purchase-orders';
import { index as tnaIndex } from '@/routes/merchandising/tna';
import { index as notificationColorsIndex } from '@/routes/settings/master-data/notification-colors';
import { index as tnaTemplatesIndex } from '@/routes/settings/master-data/tna-templates';
import type { NavGroup, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

export function AppSidebar() {
    // Hiding a link is convenience, not authorization — the routes are gated by
    // the `permission:` middleware and the module's policies.
    const canViewUsers = useCan('admin.users.view');
    const canViewRoles = useCan('admin.roles.view');
    const canViewPermissions = useCan('admin.permissions.view');
    const canViewDesignations = useCan('admin.designations.view');
    const canViewBuyers = useCan('admin.buyers.view');

    // One bucket gates every master-data table, so this same check will grow to
    // cover colours, sizes and UOM without a permission per screen.
    const canViewMasterData = useCan('settings.master-data.view');

    const canViewBqs = useCan('merchandising.bqs.view');
    const canViewPurchaseOrders = useCan('merchandising.purchase-orders.view');
    const canViewTna = useCan('merchandising.tna.view');

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
        ...(canViewBuyers
            ? [{ title: 'Buyers', href: buyersIndex(), icon: Store }]
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

    // Master data and app configuration. The *account* settings pages
    // (profile, security, appearance) are not here — they are reached from the
    // user menu and live under their own layout, see ARCHITECTURE.md §8.1.
    const settingsNavItems: NavItem[] = [
        ...(canViewMasterData
            ? [
                  {
                      title: 'Notification colours',
                      href: notificationColorsIndex(),
                      icon: Palette,
                  },
                  {
                      title: 'TNA templates',
                      href: tnaTemplatesIndex(),
                      icon: CalendarCog,
                  },
              ]
            : []),
    ];

    // The order lifecycle up to the point production begins. Tech packs, BQS and
    // bookings join this group as they are built — a module's links never join
    // another module's group (ARCHITECTURE.md §8.3).
    const merchandisingNavItems: NavItem[] = [
        ...(canViewBqs
            ? [
                  {
                      title: 'BQS',
                      href: bqsIndex(),
                      icon: Table2,
                  },
              ]
            : []),
        ...(canViewPurchaseOrders
            ? [
                  {
                      title: 'Purchase orders',
                      href: purchaseOrdersIndex(),
                      icon: ScrollText,
                  },
              ]
            : []),
        ...(canViewTna
            ? [
                  {
                      title: 'TNA',
                      href: tnaIndex(),
                      icon: CalendarClock,
                  },
              ]
            : []),
    ];

    // A group whose items are all hidden is not rendered at all, so it never
    // reaches the collapse state either.
    const navGroups: NavGroup[] = [
        { label: 'Platform', items: mainNavItems },
        { label: 'Admin', items: adminNavItems },
        { label: 'Settings', items: settingsNavItems },
        { label: 'Merchandising', items: merchandisingNavItems },
    ].filter((group) => group.items.length > 0);

    // Called once: this is the only place that knows every group and its links.
    const { isExpanded, toggle } = useNavGroups(navGroups);

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
                {navGroups.map((group) => (
                    <NavMain
                        key={group.label}
                        items={group.items}
                        label={group.label}
                        expanded={isExpanded(group.label)}
                        onToggle={() => toggle(group.label)}
                    />
                ))}
            </SidebarContent>

            {/* No footer nav: the starter kit's Repository and Documentation
                links were removed and nothing has replaced them. `NavFooter`
                stays in the tree for when something does — rendering it with an
                empty array would emit an empty group and its padding. */}
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
