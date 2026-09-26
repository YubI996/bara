import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { ApplicationSummary } from '@/types';

type Props = {
    applications: ApplicationSummary[];
    can: { create: boolean };
};

export default function ApplicationIndex({ applications, can }: Props) {
    return (
        <>
            <Head title="Aplikasi" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Aplikasi"
                    description="Sistem informasi yang dibangun dari metadata, dalam kewenangan Anda."
                    actions={
                        can.create && (
                            <Button asChild className="min-h-11 md:min-h-9">
                                <Link href={ApplicationController.create()}>
                                    <Plus aria-hidden="true" />
                                    Buat aplikasi
                                </Link>
                            </Button>
                        )
                    }
                />

                {applications.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center">
                        Belum ada aplikasi dalam kewenangan Anda.
                    </p>
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {applications.map((app) => (
                            <li
                                key={app.id}
                                className="relative rounded-lg border p-4 focus-within:ring-[3px] focus-within:ring-ring/50 hover:bg-muted/40"
                            >
                                <h2 className="font-semibold">
                                    <Link
                                        href={ApplicationController.show(app)}
                                        className="underline-offset-4 after:absolute after:inset-0 hover:underline focus:outline-none"
                                    >
                                        {app.name}
                                    </Link>
                                </h2>
                                <p className="font-mono text-sm text-muted-foreground">
                                    {app.code}
                                </p>
                                <dl className="mt-3 grid grid-cols-2 gap-1 text-sm">
                                    <dt className="text-muted-foreground">
                                        Status
                                    </dt>
                                    <dd>{app.status_label}</dd>
                                    <dt className="text-muted-foreground">
                                        Pemilik
                                    </dt>
                                    <dd>{app.owner_name ?? '—'}</dd>
                                    <dt className="text-muted-foreground">
                                        Entity
                                    </dt>
                                    <dd>{app.entities_count ?? 0}</dd>
                                </dl>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ApplicationIndex.layout = {
    breadcrumbs: [{ title: 'Aplikasi', href: ApplicationController.index() }],
};
