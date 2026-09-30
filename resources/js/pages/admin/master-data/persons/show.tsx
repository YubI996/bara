import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import MasterDataController from '@/actions/App/Modules/MasterData/Http/Controllers/MasterDataController';
import PersonController from '@/actions/App/Modules/MasterData/Http/Controllers/PersonController';
import { ConfirmAction } from '@/components/confirm-action';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { Textarea } from '@/components/form/textarea';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePemdaTimezone } from '@/hooks/use-pemda';
import { formatDate, formatDateTime } from '@/runtime/format';
import type { ParentOption } from '@/types';
import { index as personsIndex } from '@/routes/admin/master-data/persons';

type Employment = {
    id: string;
    nip: string | null;
    position: string | null;
    rank: string | null;
    org_name: string;
    valid_from: string;
    valid_to: string | null;
};

type Props = {
    person: {
        id: string;
        full_name: string;
        has_nik: boolean;
        nik_masked: string;
        birth_date: string | null;
        email: string | null;
        phone: string | null;
        updated_at: string | null;
        employments: Employment[];
    };
    revealed_nik: string | null;
    organizations: ParentOption[];
    can: { manage: boolean; reveal: boolean };
};

const EMPLOYMENT_LABELS = {
    org_id: 'Unit',
    nip: 'NIP',
    position: 'Jabatan',
    rank: 'Pangkat/golongan',
    valid_from: 'Mulai bertugas',
};

function EndEmployment({
    personId,
    employment,
}: {
    personId: string;
    employment: Employment;
}) {
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
    const id = `end-${employment.id}`;

    return (
        <ConfirmAction
            trigger="Akhiri…"
            triggerVariant="outline"
            title={`Akhiri penugasan di ${employment.org_name}?`}
            description="Penugasan tetap tersimpan sebagai riwayat dan tidak lagi muncul sebagai pilihan relasi."
            confirmLabel="Ya, akhiri"
            method="post"
            url={PersonController.endEmployment.url({
                person: personId,
                employee: employment.id,
            })}
            data={{ valid_to: date }}
        >
            <div className="grid gap-2">
                <Label htmlFor={id}>Tanggal berakhir</Label>
                <Input
                    id={id}
                    type="date"
                    value={date}
                    onChange={(e) => setDate(e.target.value)}
                    className="h-11 max-w-xs md:h-9"
                />
            </div>
        </ConfirmAction>
    );
}

export default function PersonShow({
    person,
    revealed_nik,
    organizations,
    can,
}: Props) {
    const timezone = usePemdaTimezone();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Master data', href: MasterDataController.index() },
            { title: 'Orang & pegawai', href: personsIndex() },
            { title: person.full_name },
        ],
    });

    return (
        <>
            <Head title={person.full_name} />
            <div className="space-y-8 p-4 md:p-6">
                <PageHeader
                    title={person.full_name}
                    description={`Terakhir diubah ${formatDateTime(person.updated_at, timezone)}`}
                    actions={
                        can.manage && (
                            <Button
                                asChild
                                variant="secondary"
                                className="min-h-11 md:min-h-9"
                            >
                                <Link href={PersonController.edit(person.id)}>
                                    <Pencil aria-hidden />
                                    Ubah
                                </Link>
                            </Button>
                        )
                    }
                />

                <dl className="grid max-w-3xl gap-4 sm:grid-cols-[minmax(0,14rem)_1fr]">
                    <dt className="font-medium text-muted-foreground">NIK</dt>
                    <dd className="font-mono">{person.nik_masked}</dd>
                    <dt className="font-medium text-muted-foreground">
                        Tanggal lahir
                    </dt>
                    <dd>{formatDate(person.birth_date)}</dd>
                    <dt className="font-medium text-muted-foreground">
                        Alamat email
                    </dt>
                    <dd>{person.email ?? '—'}</dd>
                    <dt className="font-medium text-muted-foreground">
                        Nomor telepon
                    </dt>
                    <dd>{person.phone ?? '—'}</dd>
                </dl>

                {revealed_nik && (
                    <div
                        role="status"
                        className="max-w-3xl rounded-md border-2 border-amber-700 p-4 dark:border-amber-400"
                    >
                        <p>
                            NIK lengkap:{' '}
                            <span className="font-mono text-lg font-semibold">
                                {revealed_nik}
                            </span>
                        </p>
                        <p className="mt-1 text-sm">
                            Pembukaan ini tercatat di audit atas nama Anda. NIK
                            tidak akan tampil lagi setelah halaman dimuat ulang.
                        </p>
                        <Link
                            href={PersonController.show(person.id)}
                            className="mt-2 inline-flex min-h-11 items-center underline underline-offset-4 md:min-h-9"
                        >
                            Sembunyikan NIK
                        </Link>
                    </div>
                )}

                {can.reveal && person.has_nik && !revealed_nik && (
                    <Form
                        {...PersonController.reveal.form(person.id)}
                        className="max-w-2xl space-y-3 rounded-lg border p-4"
                        noValidate
                    >
                        {({ errors, processing }) => (
                            <>
                                <h2 className="font-semibold">
                                    Buka NIK lengkap
                                </h2>
                                <ErrorSummary
                                    errors={errors}
                                    labels={{ reason: 'Alasan' }}
                                />
                                <Field
                                    id="reason"
                                    label="Alasan"
                                    required
                                    hint="Contoh: verifikasi data penerima bantuan atas surat Dinsos nomor … (10–500 karakter). Anda mungkin diminta mengonfirmasi kata sandi."
                                    error={errors.reason}
                                >
                                    {(aria) => (
                                        <Textarea
                                            {...aria}
                                            name="reason"
                                            rows={2}
                                            maxLength={500}
                                        />
                                    )}
                                </Field>
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={processing}
                                    className="min-h-11 md:min-h-9"
                                >
                                    {processing ? 'Membuka…' : 'Buka NIK'}
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <section
                    aria-labelledby="employments-title"
                    className="space-y-3"
                >
                    <h2
                        id="employments-title"
                        className="text-lg font-semibold"
                    >
                        Penugasan pegawai
                    </h2>
                    {person.employments.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-6 text-center">
                            Belum ada penugasan.
                        </p>
                    ) : (
                        <div
                            className="overflow-x-auto rounded-lg border"
                            role="region"
                            aria-labelledby="employments-title"
                            tabIndex={0}
                        >
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    Riwayat penugasan {person.full_name}
                                </caption>
                                <thead className="bg-muted/60">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Unit
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Jabatan
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            NIP
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Periode
                                        </th>
                                        {can.manage && (
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                <span className="sr-only">
                                                    Aksi
                                                </span>
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {person.employments.map((e) => (
                                        <tr key={e.id} className="border-t">
                                            <th
                                                scope="row"
                                                className="px-4 py-3 font-medium"
                                            >
                                                {e.org_name}
                                            </th>
                                            <td className="px-4 py-3">
                                                {[e.position, e.rank]
                                                    .filter(Boolean)
                                                    .join(', ') || '—'}
                                            </td>
                                            <td className="px-4 py-3 font-mono">
                                                {e.nip ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {formatDate(e.valid_from)} –{' '}
                                                {e.valid_to
                                                    ? formatDate(e.valid_to)
                                                    : 'sekarang'}
                                            </td>
                                            {can.manage && (
                                                <td className="px-4 py-3">
                                                    {!e.valid_to && (
                                                        <EndEmployment
                                                            personId={person.id}
                                                            employment={e}
                                                        />
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {can.manage && (
                        <Form
                            {...PersonController.addEmployment.form(person.id)}
                            className="max-w-2xl space-y-4 rounded-lg border p-4"
                            resetOnSuccess
                            noValidate
                        >
                            {({ errors, processing }) => (
                                <>
                                    <h3 className="font-medium">
                                        Tambah penugasan
                                    </h3>
                                    <ErrorSummary
                                        errors={errors}
                                        labels={EMPLOYMENT_LABELS}
                                    />
                                    <Field
                                        id="org_id"
                                        label={EMPLOYMENT_LABELS.org_id}
                                        required
                                        error={errors.org_id}
                                    >
                                        {(aria) => (
                                            <NativeSelect
                                                {...aria}
                                                name="org_id"
                                                defaultValue=""
                                            >
                                                <option value="" disabled>
                                                    Pilih unit
                                                </option>
                                                {organizations.map((o) => (
                                                    <option
                                                        key={o.value}
                                                        value={o.value}
                                                    >
                                                        {`${'— '.repeat(o.depth)}${o.label}`}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        )}
                                    </Field>
                                    <Field
                                        id="position"
                                        label={EMPLOYMENT_LABELS.position}
                                        error={errors.position}
                                    >
                                        {(aria) => (
                                            <Input
                                                {...aria}
                                                name="position"
                                                maxLength={150}
                                                className="h-11 md:h-9"
                                            />
                                        )}
                                    </Field>
                                    <Field
                                        id="rank"
                                        label={EMPLOYMENT_LABELS.rank}
                                        hint="Contoh: Penata Muda, III/a"
                                        error={errors.rank}
                                    >
                                        {(aria) => (
                                            <Input
                                                {...aria}
                                                name="rank"
                                                maxLength={60}
                                                className="h-11 max-w-xs md:h-9"
                                            />
                                        )}
                                    </Field>
                                    <Field
                                        id="nip"
                                        label={EMPLOYMENT_LABELS.nip}
                                        hint="18 digit, khusus ASN."
                                        error={errors.nip}
                                    >
                                        {(aria) => (
                                            <Input
                                                {...aria}
                                                name="nip"
                                                inputMode="numeric"
                                                maxLength={18}
                                                className="h-11 max-w-xs font-mono md:h-9"
                                            />
                                        )}
                                    </Field>
                                    <Field
                                        id="valid_from"
                                        label={EMPLOYMENT_LABELS.valid_from}
                                        required
                                        error={errors.valid_from}
                                    >
                                        {(aria) => (
                                            <Input
                                                {...aria}
                                                type="date"
                                                name="valid_from"
                                                className="h-11 max-w-xs md:h-9"
                                            />
                                        )}
                                    </Field>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-h-11 md:min-h-9"
                                    >
                                        {processing
                                            ? 'Menyimpan…'
                                            : 'Tambah penugasan'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </section>
            </div>
        </>
    );
}

PersonShow.layout = {
    breadcrumbs: [{ title: 'Orang & pegawai' }],
};
