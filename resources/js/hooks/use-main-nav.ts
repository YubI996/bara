import { usePage } from '@inertiajs/react';
import { Building2, LayoutGrid } from 'lucide-react';
import OrganizationController from '@/actions/App/Modules/Organization/Http/Controllers/OrganizationController';
import { dashboard } from '@/routes';
import type { Auth, NavItem } from '@/types';

/** Menu utama; item admin hanya muncul bila user punya permission-nya. */
export function useMainNav(): NavItem[] {
    const { auth } = usePage<{ auth: Auth }>().props;

    const items: NavItem[] = [
        { title: 'Dasbor', href: dashboard(), icon: LayoutGrid },
    ];

    if (auth.can.viewOrganizations) {
        items.push({
            title: 'Organisasi',
            href: OrganizationController.index(),
            icon: Building2,
        });
    }

    return items;
}
