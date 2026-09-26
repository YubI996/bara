import { Head } from '@inertiajs/react';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import EntityController from '@/actions/App/Modules/Metadata/Http/Controllers/EntityController';
import { PageHeader } from '@/components/page-header';
import type { ApplicationSummary, Option } from '@/types';
import { EntityForm } from './entity-form';

type Props = { application: ApplicationSummary; visibilities: Option[] };

export default function CreateEntity({ application, visibilities }: Props) {
    return (
        <>
            <Head title="Tambah entity" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Tambah entity"
                    description={`Entity baru di aplikasi ${application.name}. Setelah dibuat, tambahkan field lalu publikasikan.`}
                />
                <EntityForm
                    action={EntityController.store.form(application)}
                    visibilities={visibilities}
                    submitLabel="Buat entity"
                />
            </div>
        </>
    );
}

CreateEntity.layout = {
    breadcrumbs: [
        { title: 'Aplikasi', href: ApplicationController.index() },
        { title: 'Tambah entity', href: '#' },
    ],
};
