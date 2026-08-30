import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useId } from 'react';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/**
 * One labelled group of sidebar links. `label` names the group — modules with
 * their own surfaces get their own group rather than joining "Platform".
 *
 * The label is also the group's collapse toggle. This component owns none of
 * that state: `useNavGroups` does, from `app-sidebar.tsx` (ARCHITECTURE.md §8.3).
 *
 * The links stay flat and top-level. They are deliberately *not*
 * `SidebarMenuSub` items under a parent row — that shape is `display: none` in
 * the icon rail, which would turn a module into an icon leading nowhere.
 */
export function NavMain({
    items = [],
    label = 'Platform',
    expanded = true,
    onToggle,
}: {
    items: NavItem[];
    label?: string;
    expanded?: boolean;
    onToggle?: () => void;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { state, isMobile } = useSidebar();
    const contentId = useId();

    /*
     * The 3rem rail hides `SidebarGroupLabel` outright, so its toggle is
     * unreachable there. A group left collapsed would hide its icons with no
     * way to bring them back — so in the rail, every group is open.
     */
    const isRail = state === 'collapsed' && !isMobile;
    const isOpen = isRail || expanded;

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel asChild>
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={isOpen}
                    aria-controls={contentId}
                    className="w-full cursor-pointer justify-between hover:text-base-content"
                >
                    <span>{label}</span>
                    <ChevronDown
                        className={`transition-transform duration-200 ${isOpen ? '' : '-rotate-90'}`}
                        aria-hidden
                    />
                </button>
            </SidebarGroupLabel>

            <SidebarGroupContent id={contentId} hidden={!isOpen}>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
