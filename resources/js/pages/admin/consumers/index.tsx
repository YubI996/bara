import { Head } from '@inertiajs/react';
import { useState } from 'react';
import ConsumerController from '@/actions/App/Modules/Metadata/Http/Controllers/ConsumerController';
import { ConfirmAction } from '@/components/confirm-action';
import { ConsumerTable } from '@/components/consumer-table';
import { Textarea } from '@/components/form/textarea';
import { PageHeader } from '@/components/page-header';
import { Label } from '@/components/ui/label';
import type { ConsumerRow } from '@/types';

type Props = { pending: ConsumerRow[]; decided: ConsumerRow[] };

function NoteInput({
    id,
    value,
    onChange,
    required,
}: {
    id: string;
    value: string;
    onChange: (v: string) => void;
    required: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                Catatan keputusan
                <span className="font-normal text-muted-foreground">
                    {required ? ' (wajib, min. 10 karakter)' : ' (opsional)'}
                </span>
            </Label>
            <Textarea
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                maxLength={1000}
                rows={3}
            />
        </div>
    );
}

function Decision({ row }: { row: ConsumerRow }) {
    const [note, setNote] = useState('');
    const url = ConsumerController.decide.url({
        entity: row.entity_id,
        application: row.application_id,
    });
    const key = `${row.entity_id}-${row.application_id}`;

    if (row.status === 'pending') {
        return (
            <div className="flex flex-wrap gap-2">
                <ConfirmAction
                    trigger="Setujui…"
                    triggerVariant="default"
                    title={`Setujui ${row.application} memakai ${row.entity}?`}
                    description="Setelah disetujui, admin aplikasi dapat membuat field relasi ke entity ini dan operatornya dapat memilih datanya sesuai kewenangan baca."
                    confirmLabel="Ya, setujui"
                    processingLabel="Menyimpan…"
                    destructive={false}
                    method="post"
                    url={url}
                    data={{ decision: 'approve', note }}
                >
                    <NoteInput
                        id={`note-approve-${key}`}
                        value={note}
                        onChange={setNote}
                        required={false}
                    />
                </ConfirmAction>
                <ConfirmAction
                    trigger="Tolak…"
                    title={`Tolak pengajuan ${row.application}?`}
                    description="Pemohon melihat catatan Anda dan dapat mengajukan ulang."
                    confirmLabel="Ya, tolak"
                    processingLabel="Menyimpan…"
                    method="post"
                    url={url}
                    data={{ decision: 'reject', note }}
                >
                    <NoteInput
                        id={`note-reject-${key}`}
                        value={note}
                        onChange={setNote}
                        required
                    />
                </ConfirmAction>
            </div>
        );
    }

    if (row.status === 'approved') {
        return (
            <ConfirmAction
                trigger="Cabut…"
                title={`Cabut persetujuan ${row.application}?`}
                description="Tautan yang sudah ada tetap tersimpan, tetapi operator tidak bisa memilih data baru dan versi entity berikutnya dengan relasi ini tidak bisa dipublikasikan."
                confirmLabel="Ya, cabut"
                processingLabel="Menyimpan…"
                method="post"
                url={url}
                data={{ decision: 'revoke', note }}
            >
                <NoteInput
                    id={`note-revoke-${key}`}
                    value={note}
                    onChange={setNote}
                    required
                />
            </ConfirmAction>
        );
    }

    return null;
}

/** Halaman Walidata: keputusan pemakaian entity bersama (ADR 0016). */
export default function ConsumersIndex({ pending, decided }: Props) {
    return (
        <>
            <Head title="Pemakaian entity bersama" />
            <div className="space-y-8 p-4 md:p-6">
                <PageHeader
                    title="Pemakaian entity bersama"
                    description="Aplikasi hanya boleh merujuk entity milik aplikasi lain atau master data setelah Anda setujui."
                />

                <section aria-labelledby="pending-title" className="space-y-3">
                    <h2 id="pending-title" className="text-lg font-semibold">
                        Menunggu keputusan ({pending.length})
                    </h2>
                    {pending.length === 0 ? (
                        <p
                            role="status"
                            className="rounded-lg border border-dashed p-6 text-center"
                        >
                            Tidak ada pengajuan yang menunggu.
                        </p>
                    ) : (
                        <ConsumerTable
                            rows={pending}
                            caption="Pengajuan yang menunggu keputusan"
                            showApplication
                            actions={(row) => <Decision row={row} />}
                        />
                    )}
                </section>

                <section aria-labelledby="decided-title" className="space-y-3">
                    <h2 id="decided-title" className="text-lg font-semibold">
                        Riwayat keputusan
                    </h2>
                    {decided.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-6 text-center">
                            Belum ada keputusan.
                        </p>
                    ) : (
                        <ConsumerTable
                            rows={decided}
                            caption="Riwayat keputusan pemakaian"
                            showApplication
                            actions={(row) => <Decision row={row} />}
                        />
                    )}
                </section>
            </div>
        </>
    );
}

ConsumersIndex.layout = {
    breadcrumbs: [{ title: 'Pemakaian entity bersama' }],
};
