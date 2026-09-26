import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import EntityController from '@/actions/App/Modules/Metadata/Http/Controllers/EntityController';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { ApplicationSummary, EntitySummary } from '@/types';

type Props = {
    application: ApplicationSummary;
    entities: EntitySummary[];
    can: { update: boolean };
};

function versionText(entity: EntitySummary): string {
    const parts: string[] = [];
    if (entity.published_version) {
        parts.push(`terbit v${entity.published_version}`);
    }
    if (entity.draft_version) {
        parts.push(`draft v${entity.draft_version}`);
    }
    return parts.join(', ') || '—';
}

export default function ShowApplication({ application, entities, can }: Props) {
    return (
        <>
            <Head title={application.name} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={application.name}
                    description={
                        <>
                            <span className="font-mono">
                                {application.code}
                            </span>{' '}
                            · {application.status_label} · Pemilik:{' '}
                            {application.owner_name ?? '—'}
                        </>
                    }
                    actions={
                        can.update && (
                            <>
                                <Button
                                    asChild
                                    variant="secondary"
                                    className="min-h-11 md:min-h-9"
                                >
                                    <Link
                                        href={ApplicationController.edit(
                                            application,
                                        )}
                                    >
                                        <Pencil aria-hidden="true" />
                                        Ubah aplikasi
                                    </Link>
                                </Button>
                                <Button asChild className="min-h-11 md:min-h-9">
                                    <Link
                                        href={EntityController.create(
                                            application,
                                        )}
                                    >
                                        <Plus aria-hidden="true" />
                                        Tambah entity
                                    </Link>
                                </Button>
                            </>
                        )
                    }
                />

                {application.description && (
                    <p className="max-w-3xl">{application.description}</p>
                )}

                <section aria-labelledby="entities-title" className="space-y-3">
                    <h2 id="entities-title" className="text-lg font-semibold">
                        Entity
                    </h2>
                    {entities.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-6 text-center">
                            Belum ada entity. Entity adalah jenis data yang
                            dikelola aplikasi, misalnya “Kegiatan” atau
                            “Realisasi”.
                        </p>
                    ) : (
                        <div
                            className="overflow-x-auto rounded-lg border"
                            role="region"
                            aria-labelledby="entities-title"
                            tabIndex={0}
                        >
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    Daftar entity aplikasi {application.name}
                                </caption>
                                <thead className="bg-muted/60">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Nama
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Kode
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Versi
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Dibagikan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {entities.map((entity) => (
                                        <tr
                                            key={entity.id}
                                            className="border-t"
                                        >
                                            <th
                                                scope="row"
                                                className="px-4 py-3 font-normal"
                                            >
                                                <Link
                                                    href={EntityController.show(
                                                        entity,
                                                    )}
                                                    className="inline-flex min-h-11 items-center font-medium underline underline-offset-4 md:min-h-0"
                                                >
                                                    {entity.name}
                                                </Link>
                                            </th>
                                            <td className="px-4 py-3 font-mono">
                                                {entity.code}
                                            </td>
                                            <td className="px-4 py-3">
                                                {versionText(entity)}
                                            </td>
                                            <td className="px-4 py-3">
                                                {entity.is_shared
                                                    ? 'Ya'
                                                    : 'Tidak'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

ShowApplication.layout = {
    breadcrumbs: [{ title: 'Aplikasi', href: ApplicationController.index() }],
};
