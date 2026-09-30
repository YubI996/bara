import { Form, Head, setLayoutProps } from '@inertiajs/react';
import MasterDataController from '@/actions/App/Modules/MasterData/Http/Controllers/MasterDataController';
import { ConfirmAction } from '@/components/confirm-action';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/runtime/format';

type FiscalYear = {
    id: string;
    year: number;
    starts_on: string;
    ends_on: string;
    status: 'planning' | 'running' | 'closed';
    status_label: string;
};

type Props = { fiscal_years: FiscalYear[]; can: { manage: boolean } };

const LABELS = {
    year: 'Tahun',
    starts_on: 'Tanggal mulai',
    ends_on: 'Tanggal selesai',
};

export default function FiscalYears({ fiscal_years, can }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Master data', href: MasterDataController.index() },
            { title: 'Tahun anggaran' },
        ],
    });
    const next = (fiscal_years[0]?.year ?? new Date().getFullYear() - 1) + 1;
    const running = fiscal_years.find((f) => f.status === 'running');

    return (
        <>
            <Head title="Tahun anggaran" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Tahun anggaran"
                    description={
                        running
                            ? `Tahun anggaran berjalan: ${running.year}.`
                            : 'Belum ada tahun anggaran berjalan.'
                    }
                />

                {fiscal_years.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center">
                        Belum ada tahun anggaran.
                    </p>
                ) : (
                    <div
                        className="overflow-x-auto rounded-lg border"
                        role="region"
                        aria-labelledby="fy-caption"
                        tabIndex={0}
                    >
                        <table className="w-full text-left text-sm">
                            <caption id="fy-caption" className="sr-only">
                                Daftar tahun anggaran, terbaru di atas
                            </caption>
                            <thead className="bg-muted/60">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Tahun
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Periode
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
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
                                {fiscal_years.map((f) => (
                                    <tr key={f.id} className="border-t">
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-medium"
                                        >
                                            {f.year}
                                        </th>
                                        <td className="px-4 py-3">
                                            {formatDate(f.starts_on)} –{' '}
                                            {formatDate(f.ends_on)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {f.status_label}
                                        </td>
                                        {can.manage && (
                                            <td className="px-4 py-3">
                                                {f.status === 'planning' && (
                                                    <ConfirmAction
                                                        trigger={`Jalankan ${f.year}…`}
                                                        triggerVariant="secondary"
                                                        title={`Jalankan tahun anggaran ${f.year}?`}
                                                        description={
                                                            running
                                                                ? `Tahun anggaran ${running.year} akan ditutup otomatis.`
                                                                : 'Tahun ini menjadi tahun anggaran berjalan.'
                                                        }
                                                        confirmLabel="Ya, jalankan"
                                                        destructive={false}
                                                        method="post"
                                                        url={MasterDataController.changeFiscalYearStatus.url(
                                                            f.id,
                                                        )}
                                                        data={{
                                                            status: 'running',
                                                        }}
                                                    />
                                                )}
                                                {f.status === 'running' && (
                                                    <ConfirmAction
                                                        trigger={`Tutup ${f.year}…`}
                                                        title={`Tutup tahun anggaran ${f.year}?`}
                                                        description="Tahun yang ditutup tidak bisa dibuka kembali dan tidak lagi muncul sebagai pilihan relasi."
                                                        confirmLabel="Ya, tutup"
                                                        method="post"
                                                        url={MasterDataController.changeFiscalYearStatus.url(
                                                            f.id,
                                                        )}
                                                        data={{
                                                            status: 'closed',
                                                        }}
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
                        {...MasterDataController.storeFiscalYear.form()}
                        className="max-w-xl space-y-4 rounded-lg border p-4"
                        noValidate
                    >
                        {({ errors, processing }) => (
                            <>
                                <h2 className="font-semibold">
                                    Tambah tahun anggaran
                                </h2>
                                <ErrorSummary errors={errors} labels={LABELS} />
                                <Field
                                    id="year"
                                    label={LABELS.year}
                                    required
                                    error={errors.year}
                                >
                                    {(aria) => (
                                        <Input
                                            {...aria}
                                            name="year"
                                            inputMode="numeric"
                                            defaultValue={next}
                                            className="h-11 max-w-40 md:h-9"
                                        />
                                    )}
                                </Field>
                                <Field
                                    id="starts_on"
                                    label={LABELS.starts_on}
                                    required
                                    error={errors.starts_on}
                                >
                                    {(aria) => (
                                        <Input
                                            {...aria}
                                            type="date"
                                            name="starts_on"
                                            defaultValue={`${next}-01-01`}
                                            className="h-11 max-w-xs md:h-9"
                                        />
                                    )}
                                </Field>
                                <Field
                                    id="ends_on"
                                    label={LABELS.ends_on}
                                    required
                                    error={errors.ends_on}
                                >
                                    {(aria) => (
                                        <Input
                                            {...aria}
                                            type="date"
                                            name="ends_on"
                                            defaultValue={`${next}-12-31`}
                                            className="h-11 max-w-xs md:h-9"
                                        />
                                    )}
                                </Field>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-h-11 md:min-h-9"
                                >
                                    {processing ? 'Menyimpan…' : 'Tambah'}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

FiscalYears.layout = {
    breadcrumbs: [{ title: 'Tahun anggaran' }],
};
