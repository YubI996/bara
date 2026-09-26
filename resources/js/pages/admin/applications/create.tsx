import { Head } from '@inertiajs/react';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import { PageHeader } from '@/components/page-header';
import type { Option, ParentOption } from '@/types';
import { ApplicationForm } from './application-form';

type Props = { owners: ParentOption[]; statuses: Option[] };

export default function CreateApplication({ owners, statuses }: Props) {
    return (
        <>
            <Head title="Buat aplikasi" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Buat aplikasi"
                    description="Aplikasi adalah wadah entity, form, dan tampilan untuk satu sistem informasi."
                />
                <ApplicationForm
                    action={ApplicationController.store.form()}
                    owners={owners}
                    statuses={statuses}
                    submitLabel="Buat aplikasi"
                />
            </div>
        </>
    );
}

CreateApplication.layout = {
    breadcrumbs: [
        { title: 'Aplikasi', href: ApplicationController.index() },
        { title: 'Buat aplikasi', href: ApplicationController.create() },
    ],
};
