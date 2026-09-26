import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import EntityController from '@/actions/App/Modules/Metadata/Http/Controllers/EntityController';
import FieldController from '@/actions/App/Modules/Metadata/Http/Controllers/FieldController';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type {
    ApplicationSummary,
    DraftReport,
    EntitySummary,
    FieldSummary,
    Option,
    VersionSummary,
} from '@/types';
import { EntityForm } from './entity-form';
import { FieldTable } from './field-table';
import { PublishPanel } from './publish-panel';

type Props = {
    application: ApplicationSummary;
    entity: EntitySummary;
    draft: {
        id: string;
        version: number;
        fields: FieldSummary[];
        privacy_reviewed_at: string | null;
        report: DraftReport;
    } | null;
    published: {
        id: string;
        version: number;
        published_at: string | null;
        fields: FieldSummary[];
    } | null;
    versions: VersionSummary[];
    visibilities: Option[];
    can: { update: boolean; publish: boolean; review_privacy: boolean };
};

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleString('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

export default function ShowEntity({
    application,
    entity,
    draft,
    published,
    versions,
    visibilities,
    can,
}: Props) {
    const status = [
        published ? `Terbit v${published.version}` : 'Belum terbit',
        draft ? `draft v${draft.version}` : null,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Head title={entity.name} />
            <div className="space-y-8 p-4 md:p-6">
                <PageHeader
                    title={entity.name}
                    description={
                        <>
                            <Link
                                href={ApplicationController.show(application)}
                                className="underline underline-offset-4"
                            >
                                {application.name}
                            </Link>{' '}
                            ·{' '}
                            <span className="font-mono">
                                {application.code}.{entity.code}
                            </span>{' '}
                            · {status}
                        </>
                    }
                    actions={
                        can.update &&
                        !draft &&
                        published && (
                            <Button
                                type="button"
                                className="min-h-11 md:min-h-9"
                                onClick={() =>
                                    router.post(
                                        EntityController.createDraft.url(
                                            entity,
                                        ),
                                    )
                                }
                            >
                                Buat draft baru
                            </Button>
                        )
                    }
                />

                {draft && (
                    <section
                        aria-labelledby="draft-title"
                        className="space-y-4"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-2">
                            <h2
                                id="draft-title"
                                className="text-lg font-semibold"
                            >
                                Field draft v{draft.version}
                            </h2>
                            {can.update && (
                                <div className="flex flex-wrap gap-2">
                                    {published && (
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            className="min-h-11 md:min-h-9"
                                            onClick={() =>
                                                router.delete(
                                                    EntityController.discardDraft.url(
                                                        entity,
                                                    ),
                                                    {
                                                        onBefore: () =>
                                                            window.confirm(
                                                                `Buang draft v${draft.version}? Semua perubahan yang belum terbit hilang.`,
                                                            ),
                                                    },
                                                )
                                            }
                                        >
                                            Buang draft
                                        </Button>
                                    )}
                                    <Button
                                        asChild
                                        className="min-h-11 md:min-h-9"
                                    >
                                        <Link
                                            href={FieldController.create(
                                                entity,
                                            )}
                                        >
                                            <Plus aria-hidden="true" />
                                            Tambah field
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </div>
                        <FieldTable
                            entity={entity}
                            fields={draft.fields}
                            caption={`Field draft versi ${draft.version}`}
                            editable={can.update}
                        />
                        <PublishPanel
                            entity={entity}
                            version={draft.version}
                            report={draft.report}
                            privacyReviewedAt={draft.privacy_reviewed_at}
                            can={can}
                        />
                    </section>
                )}

                {published && (
                    <section
                        aria-labelledby="published-title"
                        className="space-y-3"
                    >
                        <h2
                            id="published-title"
                            className="text-lg font-semibold"
                        >
                            Field versi terbit v{published.version}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Terbit {formatDate(published.published_at)}. Versi
                            terbit tidak dapat diubah; perubahan dilakukan lewat
                            draft baru.
                        </p>
                        <FieldTable
                            entity={entity}
                            fields={published.fields}
                            caption={`Field versi terbit ${published.version}`}
                            editable={false}
                        />
                    </section>
                )}

                {can.update && (
                    <details className="rounded-lg border p-4">
                        <summary className="cursor-pointer text-lg font-semibold">
                            Pengaturan entity
                        </summary>
                        <div className="mt-4">
                            <EntityForm
                                action={EntityController.update.form(entity)}
                                visibilities={visibilities}
                                entity={entity}
                                submitLabel="Simpan pengaturan"
                            />
                        </div>
                    </details>
                )}

                <section aria-labelledby="versions-title" className="space-y-3">
                    <h2 id="versions-title" className="text-lg font-semibold">
                        Riwayat versi
                    </h2>
                    {versions.length === 0 ? (
                        <p>Belum ada versi terbit.</p>
                    ) : (
                        <ol className="space-y-2">
                            {versions.map((v) => (
                                <li
                                    key={v.id}
                                    className="rounded-md border p-3"
                                >
                                    <p className="font-medium">
                                        Versi {v.version}{' '}
                                        {v.status === 'published'
                                            ? '(aktif)'
                                            : '(digantikan)'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {formatDate(v.published_at)}
                                    </p>
                                    {v.change_summary && (
                                        <p className="text-sm">
                                            {v.change_summary}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </>
    );
}

ShowEntity.layout = {
    breadcrumbs: [{ title: 'Aplikasi', href: ApplicationController.index() }],
};
