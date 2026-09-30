import { usePage } from '@inertiajs/react';
import {
    Boxes,
    Building2,
    Database,
    LayoutGrid,
    Library,
    ShieldCheck,
} from 'lucide-react';
import RecordController from '@/actions/App/Modules/Data/Http/Controllers/RecordController';
import MasterDataController from '@/actions/App/Modules/MasterData/Http/Controllers/MasterDataController';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import ConsumerController from '@/actions/App/Modules/Metadata/Http/Controllers/ConsumerController';
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

    if (auth.can.viewMasterData) {
        items.push({
            title: 'Master data',
            href: MasterDataController.index(),
            icon: Library,
        });
    }

    if (auth.can.approveConsumers) {
        items.push({
            title: 'Persetujuan pemakaian',
            href: ConsumerController.index(),
            icon: ShieldCheck,
        });
    }

    return items;
}
