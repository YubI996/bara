import { Head } from '@inertiajs/react';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import { PageHeader } from '@/components/page-header';
import type { ApplicationSummary, Option, ParentOption } from '@/types';
import { ApplicationForm } from './application-form';

type Props = {
    application: ApplicationSummary;
    owners: ParentOption[];
    statuses: Option[];
};

export default function EditApplication({
    application,
    owners,
    statuses,
}: Props) {
    return (
        <>
            <Head title={`Ubah ${application.name}`} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader title={`Ubah aplikasi: ${application.name}`} />
                <ApplicationForm
                    action={ApplicationController.update.form(application)}
                    owners={owners}
                    statuses={statuses}
                    application={application}
                    submitLabel="Simpan perubahan"
                />
            </div>
        </>
    );
}

EditApplication.layout = {
    breadcrumbs: [
        { title: 'Aplikasi', href: ApplicationController.index() },
        { title: 'Ubah aplikasi', href: '#' },
    ],
};
