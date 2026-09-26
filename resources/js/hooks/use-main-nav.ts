import { usePage } from '@inertiajs/react';
import { Boxes, Building2, Database, LayoutGrid } from 'lucide-react';
import RecordController from '@/actions/App/Modules/Data/Http/Controllers/RecordController';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import OrganizationController from '@/actions/App/Modules/Organization/Http/Controllers/OrganizationController';
import { dashboard } from '@/routes';
import type { Auth, NavItem } from '@/types';

/** Menu utama; item admin hanya muncul bila user punya permission-nya. */
export function useMainNav(): NavItem[] {
    const { auth } = usePage<{ auth: Auth }>().props;

    const items: NavItem[] = [
        { title: 'Dasbor', href: dashboard(), icon: LayoutGrid },
        { title: 'Data', href: RecordController.home(), icon: Database },
    ];

    if (auth.can.viewOrganizations) {
        items.push({
            title: 'Organisasi',
            href: OrganizationController.index(),
            icon: Building2,
        });
    }

    if (auth.can.viewApplications) {
        items.push({
            title: 'Aplikasi',
            href: ApplicationController.index(),
            icon: Boxes,
        });
    }

    return items;
}
