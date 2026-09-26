import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Menu</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    // Aktif juga di halaman turunan (/apps/x/y di bawah "Data"), UX-014.
                    const active = isCurrentOrParentUrl(item.href);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                // Penanda non-warna: garis kiri tebal (A11Y-014).
                                className="data-[active=true]:font-semibold data-[active=true]:shadow-[inset_4px_0_0_var(--sidebar-foreground)]"
                            >
                                <Link
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    prefetch
                                    // Di ponsel, tutup sheet setelah memilih menu agar konten tidak tertutup.
                                    onClick={() =>
                                        isMobile && setOpenMobile(false)
                                    }
                                >
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
