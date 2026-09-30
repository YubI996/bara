import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Search } from 'lucide-react';
import MasterDataController from '@/actions/App/Modules/MasterData/Http/Controllers/MasterDataController';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Region = {
    code: string;
    name: string;
    level: number;
    level_label: string;
    has_children: boolean;
};

type Props = {
    items: Region[];
    total: number;
    page: number;
    last_page: number;
    parent: { code: string; name: string } | null;
    trail: { code: string; name: string }[];
    q: string;
};

const number = new Intl.NumberFormat('id-ID');

/** Penjelajah kode wilayah Kemendagri (hanya baca). */
export default function Regions({
    items,
    total,
    page,
    last_page,
    parent,
    trail,
    q,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Master data', href: MasterDataController.index() },
            { title: 'Wilayah' },
        ],
    });

    const pageLink = (p: number) =>
        MasterDataController.regions({
            query: { parent: parent?.code, q: q || undefined, page: p },
        });

    return (
        <>
            <Head title="Wilayah" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={parent ? parent.name : 'Wilayah'}
                    description={
                        q
                            ? `Hasil pencarian “${q}”: ${number.format(total)} wilayah.`
                            : `${number.format(total)} wilayah pada tingkat ini.`
                    }
                />

                <Form
                    {...MasterDataController.regions.form()}
                    role="search"
                    aria-label="Cari wilayah"
                    className="flex flex-col gap-3 md:flex-row md:items-end"
                >
                    <div className="grid gap-2 md:w-96">
                        <Label htmlFor="q">Cari nama atau kode wilayah</Label>
                        <Input
                            id="q"
                            name="q"
                            type="search"
                            defaultValue={q}
                            maxLength={100}
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

                {!q && trail.length > 0 && (
                    <nav aria-label="Tingkat wilayah">
                        <ol className="flex flex-wrap items-center gap-2 text-sm">
                            <li>
                                <Link
                                    href={MasterDataController.regions()}
                                    className="underline underline-offset-4"
                                >
                                    Semua provinsi
                                </Link>
                            </li>
                            {trail.map((t, i) => (
                                <li
                                    key={t.code}
                                    className="flex items-center gap-2"
                                >
                                    <span aria-hidden>/</span>
                                    {i === trail.length - 1 ? (
                                        <span
                                            aria-current="page"
                                            className="font-medium"
                                        >
                                            {t.name}
                                        </span>
                                    ) : (
                                        <Link
                                            href={MasterDataController.regions({
                                                query: { parent: t.code },
                                            })}
                                            className="underline underline-offset-4"
                                        >
                                            {t.name}
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </nav>
                )}

                {items.length === 0 ? (
                    <p
                        role="status"
                        className="rounded-lg border border-dashed p-6 text-center"
                    >
                        {q
                            ? 'Tidak ada wilayah yang cocok.'
                            : 'Belum ada data wilayah. Jalankan php artisan bara:import-regions.'}
                    </p>
                ) : (
                    <div
                        className="overflow-x-auto rounded-lg border"
                        role="region"
                        aria-labelledby="regions-caption"
                        tabIndex={0}
                    >
                        <table className="w-full text-left text-sm">
                            <caption id="regions-caption" className="sr-only">
                                Daftar wilayah, urut kode
                            </caption>
                            <thead className="bg-muted/60">
                                <tr>
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
                                        Nama
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Tingkat
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((r) => (
                                    <tr key={r.code} className="border-t">
                                        <td className="px-4 py-3 font-mono">
                                            {r.code}
                                        </td>
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-normal"
                                        >
                                            {r.has_children ? (
                                                <Link
                                                    href={MasterDataController.regions(
                                                        {
                                                            query: {
                                                                parent: r.code,
                                                            },
                                                        },
                                                    )}
                                                    className="inline-flex min-h-11 items-center font-medium underline underline-offset-4 md:min-h-0"
                                                >
                                                    {r.name}
                                                </Link>
                                            ) : (
                                                r.name
                                            )}
                                        </th>
                                        <td className="px-4 py-3">
                                            {r.level_label}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {last_page > 1 && (
                    <nav
                        aria-label="Halaman"
                        className="flex items-center gap-3"
                    >
                        {page > 1 && (
                            <Button
                                asChild
                                variant="secondary"
                                className="min-h-11 md:min-h-9"
                            >
                                <Link href={pageLink(page - 1)}>
                                    Sebelumnya
                                </Link>
                            </Button>
                        )}
                        <span>
                            Halaman {page} dari {last_page}
                        </span>
                        {page < last_page && (
                            <Button
                                asChild
                                variant="secondary"
                                className="min-h-11 md:min-h-9"
                            >
                                <Link href={pageLink(page + 1)}>
                                    Berikutnya
                                </Link>
                            </Button>
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

Regions.layout = {
    breadcrumbs: [{ title: 'Wilayah' }],
};
