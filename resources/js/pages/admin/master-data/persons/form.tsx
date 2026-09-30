import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import MasterDataController from '@/actions/App/Modules/MasterData/Http/Controllers/MasterDataController';
import PersonController from '@/actions/App/Modules/MasterData/Http/Controllers/PersonController';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as personsIndex } from '@/routes/admin/master-data/persons';

type Person = {
    id: string;
    full_name: string;
    has_nik: boolean;
    nik_masked: string;
    birth_date: string | null;
    email: string | null;
    phone: string | null;
};

const LABELS = {
    full_name: 'Nama lengkap',
    nik: 'NIK',
    birth_date: 'Tanggal lahir',
    email: 'Alamat email',
    phone: 'Nomor telepon',
    person: 'Data orang',
};

export default function PersonForm({ person }: { person: Person | null }) {
    const title = person ? `Ubah ${person.full_name}` : 'Tambah orang';
    setLayoutProps({
        breadcrumbs: [
            { title: 'Master data', href: MasterDataController.index() },
            { title: 'Orang & pegawai', href: personsIndex() },
            ...(person
                ? [
                      {
                          title: person.full_name,
                          href: PersonController.show(person.id),
                      },
                  ]
                : []),
            { title: person ? 'Ubah' : 'Tambah' },
        ],
    });
    const action = person
        ? PersonController.update.form(person.id)
        : PersonController.store.form();

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description="Data pribadi: isi hanya yang diperlukan (prinsip minimisasi data, UU 27/2022)."
                />
                <Form {...action} className="max-w-2xl space-y-6" noValidate>
                    {({ errors, processing }) => (
                        <>
                            <ErrorSummary errors={errors} labels={LABELS} />
                            <Field
                                id="full_name"
                                label={LABELS.full_name}
                                required
                                error={errors.full_name}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        name="full_name"
                                        defaultValue={person?.full_name}
                                        maxLength={150}
                                        autoComplete="off"
                                        className="h-11 md:h-9"
                                    />
                                )}
                            </Field>
                            <Field
                                id="nik"
                                label={LABELS.nik}
                                hint={
                                    person?.has_nik
                                        ? `Tersimpan: ${person.nik_masked}. Kosongkan bila tidak diubah.`
                                        : '16 digit. Disimpan terenkripsi; hanya Walidata yang bisa membukanya.'
                                }
                                error={errors.nik}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        name="nik"
                                        inputMode="numeric"
                                        autoComplete="off"
                                        maxLength={24}
                                        className="h-11 max-w-xs font-mono md:h-9"
                                    />
                                )}
                            </Field>
                            <Field
                                id="birth_date"
                                label={LABELS.birth_date}
                                error={errors.birth_date}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        type="date"
                                        name="birth_date"
                                        defaultValue={person?.birth_date ?? ''}
                                        className="h-11 max-w-xs md:h-9"
                                    />
                                )}
                            </Field>
                            <Field
                                id="email"
                                label={LABELS.email}
                                error={errors.email}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        type="email"
                                        name="email"
                                        defaultValue={person?.email ?? ''}
                                        autoComplete="off"
                                        className="h-11 md:h-9"
                                    />
                                )}
                            </Field>
                            <Field
                                id="phone"
                                label={LABELS.phone}
                                hint="Contoh: 0812 3456 7890"
                                error={errors.phone}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        type="tel"
                                        name="phone"
                                        defaultValue={person?.phone ?? ''}
                                        autoComplete="off"
                                        className="h-11 max-w-xs md:h-9"
                                    />
                                )}
                            </Field>
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-h-11 md:min-h-9"
                                >
                                    {processing ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                                <Button
                                    asChild
                                    variant="secondary"
                                    className="min-h-11 md:min-h-9"
                                >
                                    <Link
                                        href={
                                            person
                                                ? PersonController.show(
                                                      person.id,
                                                  )
                                                : personsIndex()
                                        }
                                    >
                                        Batal
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PersonForm.layout = {
    breadcrumbs: [{ title: 'Orang & pegawai' }],
};
