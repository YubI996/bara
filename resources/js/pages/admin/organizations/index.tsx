import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import OrganizationController from '@/actions/App/Modules/Organization/Http/Controllers/OrganizationController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { OrganizationRow } from '@/types';

type Props = {
    organizations: OrganizationRow[];
    filters: { q: string };
    can: { create: boolean };
    limit: number;
};

export default function OrganizationIndex({
    organizations,
    filters,
    can,
    limit,
}: Props) {
    const summary = filters.q
        ? `${organizations.length} unit cocok dengan “${filters.q}”.`
        : `${organizations.length} unit dalam kewenangan Anda.`;

    return (
        <>
            <Head title="Struktur organisasi" />
            <div className="space-y-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Struktur organisasi
                        </h1>
                        <p className="text-muted-foreground">
                            Master data unit kerja Pemda. Dipakai bersama oleh
                            semua aplikasi.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild className="min-h-11 md:min-h-9">
                            <Link href={OrganizationController.create()}>
                                <Plus aria-hidden="true" />
                                Tambah unit
                            </Link>
                        </Button>
                    )}
                </header>

                <Form
                    {...OrganizationController.index.form()}
                    role="search"
                    aria-label="Cari unit organisasi"
                    className="flex max-w-xl flex-col gap-2 sm:flex-row sm:items-end"
                    options={{ preserveState: true, replace: true }}
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="q">
                            Cari nama, singkatan, atau kode
                        </Label>
                        <Input
                            id="q"
                            name="q"
                            type="search"
                            defaultValue={filters.q}
                            maxLength={100}
                            className="h-11 md:h-9"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        className="min-h-11 md:min-h-9"
                    >
                        <Search aria-hidden="true" />
                        Cari
                    </Button>
                </Form>

                <p role="status" className="text-sm text-muted-foreground">
                    {summary}
                    {organizations.length >= limit &&
                        ` Hanya ${limit} unit pertama yang ditampilkan; persempit pencarian.`}
                </p>

                {organizations.length > 0 ? (
                    <div
                        className="overflow-x-auto rounded-lg border"
                        role="region"
                        aria-labelledby="organizations-caption"
                        tabIndex={0}
                    >
                        <table className="w-full text-left text-sm">
                            <caption
                                id="organizations-caption"
                                className="sr-only"
                            >
                                Daftar unit organisasi, diurutkan menurut
                                hierarki
                            </caption>
                            <thead className="bg-muted/60">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Nama unit
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
                                        Jenis
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {organizations.map((org) => (
                                    <tr key={org.id} className="border-t">
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-normal"
                                        >
                                            <span
                                                className="block"
                                                style={{
                                                    paddingInlineStart: `${org.depth * 1.25}rem`,
                                                }}
                                            >
                                                <span className="sr-only">
                                                    Tingkat {org.depth + 1}
                                                    :{' '}
                                                </span>
                                                <span className="font-medium">
                                                    {org.name}
                                                </span>
                                                {org.short_name && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        ({org.short_name})
                                                    </span>
                                                )}
                                            </span>
                                        </th>
                                        <td className="px-4 py-3 font-mono">
                                            {org.code}
                                        </td>
                                        <td className="px-4 py-3">
                                            {org.kind_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            {org.is_active ? (
                                                <span className="inline-flex items-center rounded-md border border-green-700 px-2 py-0.5 text-xs font-medium text-green-800 dark:border-green-400 dark:text-green-200">
                                                    Aktif
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center rounded-md border border-neutral-500 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                                    Nonaktif sejak{' '}
                                                    {org.valid_to}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {org.can_update &&
                                                org.is_active && (
                                                    <Link
                                                        href={OrganizationController.edit(
                                                            org,
                                                        )}
                                                        className="inline-flex min-h-11 items-center font-medium underline underline-offset-4 md:min-h-0"
                                                    >
                                                        Ubah
                                                        <span className="sr-only">
                                                            {' '}
                                                            {org.name}
                                                        </span>
                                                    </Link>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="rounded-lg border border-dashed p-6 text-center">
                        {filters.q
                            ? 'Tidak ada unit yang cocok. Coba kata kunci lain.'
                            : 'Belum ada unit dalam kewenangan Anda.'}
                    </p>
                )}
            </div>
        </>
    );
}

OrganizationIndex.layout = {
    breadcrumbs: [
        { title: 'Organisasi', href: OrganizationController.index() },
    ],
};
