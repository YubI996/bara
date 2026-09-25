import { Head } from '@inertiajs/react';
import OrganizationController from '@/actions/App/Modules/Organization/Http/Controllers/OrganizationController';
import type { Option, ParentOption } from '@/types';
import { OrganizationForm } from './organization-form';

type Props = {
    parents: ParentOption[];
    kinds: Option[];
    defaultParentId: string | null;
};

export default function CreateOrganization({
    parents,
    kinds,
    defaultParentId,
}: Props) {
    return (
        <>
            <Head title="Tambah unit organisasi" />
            <div className="space-y-6 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Tambah unit organisasi
                    </h1>
                    <p className="text-muted-foreground">
                        Unit baru langsung tersedia sebagai master data untuk
                        semua aplikasi.
                    </p>
                </header>
                <OrganizationForm
                    action={OrganizationController.store.form()}
                    parents={parents}
                    kinds={kinds}
                    defaultParentId={defaultParentId}
                    submitLabel="Simpan unit"
                />
            </div>
        </>
    );
}

CreateOrganization.layout = {
    breadcrumbs: [
        { title: 'Organisasi', href: OrganizationController.index() },
        { title: 'Tambah unit', href: OrganizationController.create() },
    ],
};
