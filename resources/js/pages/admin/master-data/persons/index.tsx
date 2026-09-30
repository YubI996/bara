import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import MasterDataController from '@/actions/App/Modules/MasterData/Http/Controllers/MasterDataController';
import PersonController from '@/actions/App/Modules/MasterData/Http/Controllers/PersonController';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { search as personsSearch } from '@/routes/admin/master-data/persons';

type Person = {
    id: string;
    full_name: string;
    nik_masked: string;
    position: string | null;
    org_name: string | null;
};

type Props = {
    persons: Person[];
    q: string;
    searched_nik: boolean;
    limit: number;
    can: { manage: boolean };
};

export default function PersonsIndex({
    persons,
    q,
    searched_nik,
    limit,
    can,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Master data', href: MasterDataController.index() },
            { title: 'Orang & pegawai' },
        ],
    });

    return (
        <>
            <Head title="Orang & pegawai" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Orang & pegawai"
                    description="Data pribadi (UU 27/2022). NIK hanya ditampilkan empat digit terakhir."
                    actions={
                        can.manage && (
                            <Button asChild className="min-h-11 md:min-h-9">
                                <Link href={PersonController.create()}>
                                    <Plus aria-hidden />
                                    Tambah orang
                                </Link>
                            </Button>
                        )
                    }
                />

                <Form
                    // POST: NIK tidak boleh masuk URL (riwayat browser & log server).
                    {...personsSearch.form()}
                    role="search"
                    aria-label="Cari orang"
                    className="flex flex-col gap-3 md:flex-row md:items-end"
                >
                    <div className="grid gap-2 md:w-96">
                        <Label htmlFor="q">
                            Cari nama, atau NIK lengkap (16 digit)
                        </Label>
                        <p
                            id="q-hint"
                            className="text-sm text-muted-foreground"
                        >
                            NIK dicocokkan persis tanpa disimpan di riwayat
                            pencarian.
                        </p>
                        <Input
                            id="q"
                            name="q"
                            type="search"
                            defaultValue={q}
                            maxLength={100}
                            autoComplete="off"
                            aria-describedby="q-hint"
                            className="h-11 md:h-9"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        className="min-h-11 md:min-h-9"
                    >
                        <Search aria-hidden />
                        Cari
                    </Button>
                </Form>

                {searched_nik && (
                    <p role="status" className="text-sm">
                        Hasil pencarian NIK:{' '}
                        {persons.length === 0
                            ? 'tidak ditemukan.'
                            : 'ditemukan.'}
                    </p>
                )}

                {persons.length === 0 ? (
                    <p
                        role="status"
                        className="rounded-lg border border-dashed p-6 text-center"
                    >
                        {q || searched_nik
                            ? 'Tidak ada orang yang cocok.'
                            : 'Belum ada data orang.'}
                    </p>
                ) : (
                    <div
                        className="overflow-x-auto rounded-lg border"
                        role="region"
                        aria-labelledby="persons-caption"
                        tabIndex={0}
                    >
                        <table className="w-full text-left text-sm">
                            <caption id="persons-caption" className="sr-only">
                                Daftar orang, maksimal {limit} hasil, urut nama
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
                                        NIK
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Penugasan aktif
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {persons.map((p) => (
                                    <tr key={p.id} className="border-t">
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-normal"
                                        >
                                            <Link
                                                href={PersonController.show(
                                                    p.id,
                                                )}
                                                className="inline-flex min-h-11 items-center font-medium underline underline-offset-4 md:min-h-0"
                                            >
                                                {p.full_name}
                                            </Link>
                                        </th>
                                        <td className="px-4 py-3 font-mono">
                                            {p.nik_masked}
                                        </td>
                                        <td className="px-4 py-3">
                                            {p.org_name
                                                ? `${p.position ?? 'Pegawai'} — ${p.org_name}`
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

PersonsIndex.layout = {
    breadcrumbs: [{ title: 'Orang & pegawai' }],
};
