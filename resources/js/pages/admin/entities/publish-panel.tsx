import { useState } from 'react';
import EntityController from '@/actions/App/Modules/Metadata/Http/Controllers/EntityController';
import { ConfirmAction } from '@/components/confirm-action';
import { Field } from '@/components/form/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { DraftReport, EntitySummary, FieldChange } from '@/types';

type Props = {
    entity: EntitySummary;
    version: number;
    report: DraftReport;
    privacyReviewedAt: string | null;
    can: { publish: boolean; review_privacy: boolean; update: boolean };
};

const categoryStyle: Record<FieldChange['category'], string> = {
    safe: 'border-green-700 text-green-900 dark:border-green-400 dark:text-green-100',
    warning:
        'border-amber-700 text-amber-900 dark:border-amber-400 dark:text-amber-100',
    migration:
        'border-sky-700 text-sky-900 dark:border-sky-400 dark:text-sky-100',
    blocked:
        'border-red-700 text-red-900 dark:border-red-400 dark:text-red-100',
};

const kindLabel: Record<FieldChange['kind'], string> = {
    added: 'Ditambah',
    modified: 'Diubah',
    removed: 'Dihapus',
};

export function PublishPanel({
    entity,
    version,
    report,
    privacyReviewedAt,
    can,
}: Props) {
    const needsReview =
        report.requires_privacy_review && privacyReviewedAt === null;
    const ready = report.can_publish && !needsReview;
    const [note, setNote] = useState('');
    const count = (kind: FieldChange['kind']) =>
        report.changes.filter((c) => c.kind === kind).length;
    const migrations = report.changes.filter(
        (c) => c.category === 'migration',
    ).length;

    return (
        <section
            aria-labelledby="publish-title"
            className="space-y-4 rounded-lg border p-4"
        >
            <h2 id="publish-title" className="text-lg font-semibold">
                Kesiapan publikasi draft v{version}
            </h2>

            <p role="status" className="font-medium">
                {ready
                    ? 'Draft siap dipublikasikan.'
                    : 'Draft belum bisa dipublikasikan. Periksa daftar berikut.'}
            </p>

            {report.blockers.length > 0 && (
                <div>
                    <h3 className="font-medium">Yang harus diperbaiki</h3>
                    <ul className="mt-1 list-disc space-y-1 pl-5 text-red-800 dark:text-red-200">
                        {report.blockers.map((b) => (
                            <li key={b}>{b}</li>
                        ))}
                    </ul>
                </div>
            )}

            {report.warnings.length > 0 && (
                <div>
                    <h3 className="font-medium">Peringatan</h3>
                    <ul className="mt-1 list-disc space-y-1 pl-5">
                        {report.warnings.map((w) => (
                            <li key={w}>{w}</li>
                        ))}
                    </ul>
                </div>
            )}

            {report.changes.length > 0 && (
                <div className="space-y-2">
                    <h3 className="font-medium">
                        Perubahan dibanding versi terbit
                    </h3>
                    <ul className="space-y-2">
                        {report.changes.map((change) => (
                            <li
                                key={change.field_key}
                                className="rounded-md border p-3"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <span
                                        className={cn(
                                            'inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold',
                                            categoryStyle[change.category],
                                        )}
                                    >
                                        {change.category_label}
                                    </span>
                                    <span className="font-medium">
                                        {kindLabel[change.kind]}: {change.label}
                                    </span>
                                    <span className="font-mono text-sm text-muted-foreground">
                                        {change.code}
                                    </span>
                                </div>
                                <ul className="mt-1 list-disc pl-5 text-sm">
                                    {change.messages.map((m) => (
                                        <li key={m}>{m}</li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {report.requires_privacy_review && (
                <div className="space-y-2 rounded-md border border-amber-700 p-3 dark:border-amber-400">
                    <h3 className="font-medium">Persetujuan Pejabat PDP</h3>
                    <p className="text-sm">
                        {privacyReviewedAt
                            ? `Disetujui pada ${new Date(privacyReviewedAt).toLocaleString('id-ID')}.`
                            : 'Draft memuat perubahan field data pribadi (UU 27/2022). Pejabat PDP harus menyetujui sebelum publikasi. Persetujuan batal bila draft diubah lagi.'}
                    </p>
                    {!privacyReviewedAt && can.review_privacy && (
                        <ConfirmAction
                            trigger="Setujui field data pribadi…"
                            triggerVariant="secondary"
                            title={`Setujui data pribadi pada draft v${version}?`}
                            description="Persetujuan ini tercatat atas nama Anda sebagai Pejabat PDP (UU 27/2022) dan batal bila draft diubah lagi. Periksa daftar perubahan berikut."
                            confirmLabel="Ya, saya setujui"
                            processingLabel="Menyimpan persetujuan…"
                            destructive={false}
                            method="post"
                            url={EntityController.reviewPrivacy.url(entity)}
                        >
                            <ul className="max-h-60 list-disc space-y-1 overflow-y-auto pl-5 text-sm">
                                {report.changes.map((c) => (
                                    <li key={c.field_key}>
                                        {kindLabel[c.kind]}: {c.label} (
                                        <span className="font-mono">
                                            {c.code}
                                        </span>
                                        )
                                    </li>
                                ))}
                            </ul>
                        </ConfirmAction>
                    )}
                </div>
            )}

            {can.publish && (
                <div className="space-y-3">
                    <Field
                        id="note"
                        label="Catatan rilis"
                        hint="Ringkasan perubahan untuk riwayat versi."
                    >
                        {(aria) => (
                            <Input
                                {...aria}
                                name="note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                maxLength={500}
                                className="h-11 md:h-9"
                            />
                        )}
                    </Field>
                    {ready ? (
                        <ConfirmAction
                            trigger={`Publikasikan versi ${version}…`}
                            triggerVariant="default"
                            title={`Publikasikan versi ${version}?`}
                            description="Versi terbit tidak dapat diubah dan langsung berlaku untuk semua operator."
                            confirmLabel={`Ya, publikasikan versi ${version}`}
                            processingLabel="Mempublikasikan…"
                            destructive={false}
                            method="post"
                            url={EntityController.publish.url(entity)}
                            data={{ note }}
                        >
                            <ul className="list-disc space-y-1 pl-5 text-sm">
                                <li>{count('added')} field ditambah</li>
                                <li>{count('modified')} field diubah</li>
                                <li>{count('removed')} field dihapus</li>
                                {migrations > 0 && (
                                    <li className="font-medium">
                                        {migrations} perubahan memigrasi data
                                        lama di latar belakang
                                    </li>
                                )}
                                <li>
                                    Catatan rilis: {note.trim() || '(kosong)'}
                                </li>
                            </ul>
                        </ConfirmAction>
                    ) : (
                        <Button
                            type="button"
                            disabled
                            aria-describedby="publish-title"
                            className="min-h-11 md:min-h-9"
                        >
                            Publikasikan versi {version}
                        </Button>
                    )}
                </div>
            )}
        </section>
    );
}
