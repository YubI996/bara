import { Head, Link } from '@inertiajs/react';
import RecordController from '@/actions/App/Modules/Data/Http/Controllers/RecordController';
import { PageHeader } from '@/components/page-header';

type Props = {
    applications: {
        code: string;
        name: string;
        entities: { code: string; name: string }[];
    }[];
};

export default function RuntimeHome({ applications }: Props) {
    return (
        <>
            <Head title="Data" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Data"
                    description="Jenis data yang boleh Anda lihat atau kelola."
                />
                {applications.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center">
                        Belum ada data yang bisa Anda akses. Minta admin
                        aplikasi memberi Anda peran (operator atau pembaca).
                    </p>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {applications.map((app) => (
                            <section
                                key={app.code}
                                aria-labelledby={`app-${app.code}`}
                                className="rounded-lg border p-4"
                            >
                                <h2
                                    id={`app-${app.code}`}
                                    className="font-semibold"
                                >
                                    {app.name}
                                </h2>
                                <ul className="mt-2 grid gap-1">
                                    {app.entities.map((entity) => (
                                        <li key={entity.code}>
                                            <Link
                                                href={RecordController.index({
                                                    app: app.code,
                                                    entity: entity.code,
                                                })}
                                                className="inline-flex min-h-11 items-center underline underline-offset-4 md:min-h-9"
                                            >
                                                {entity.name}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

RuntimeHome.layout = {
    breadcrumbs: [{ title: 'Data', href: RecordController.home() }],
};
