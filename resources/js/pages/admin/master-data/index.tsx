import { Head, Link } from '@inertiajs/react';
import { CalendarRange, MapPinned, UsersRound } from 'lucide-react';
import MasterDataController from '@/actions/App/Modules/MasterData/Http/Controllers/MasterDataController';
import { PageHeader } from '@/components/page-header';
import { index as personsIndex } from '@/routes/admin/master-data/persons';

type Props = {
    counts: {
        regions: number;
        persons: number;
        employees: number;
        fiscal_years: number;
        source_ref: string | null;
    };
    can: { manage: boolean };
};

const number = new Intl.NumberFormat('id-ID');

/** Master data bersama (data induk, Perpres 39/2019) yang dirujuk semua aplikasi. */
export default function MasterDataIndex({ counts }: Props) {
    const cards = [
        {
            title: 'Wilayah',
            href: MasterDataController.regions(),
            icon: MapPinned,
            text: `${number.format(counts.regions)} kode wilayah aktif.`,
            note: counts.source_ref
                ? `Sumber: ${counts.source_ref}`
                : 'Belum diimpor. Jalankan php artisan bara:import-regions.',
        },
        {
            title: 'Orang & pegawai',
            href: personsIndex(),
            icon: UsersRound,
            text: `${number.format(counts.persons)} orang, ${number.format(counts.employees)} penugasan pegawai aktif.`,
            note: 'NIK disimpan terenkripsi dan hanya bisa dibuka Walidata.',
        },
        {
            title: 'Tahun anggaran',
            href: MasterDataController.fiscalYears(),
            icon: CalendarRange,
            text: `${number.format(counts.fiscal_years)} tahun anggaran.`,
            note: 'Satu tahun anggaran berjalan pada satu waktu.',
        },
    ];

    return (
        <>
            <Head title="Master data" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Master data"
                    description="Data induk bersama yang dirujuk semua aplikasi. Aplikasi hanya boleh merujuknya setelah pemakaiannya disetujui Walidata."
                />
                <ul className="grid gap-4 md:grid-cols-3">
                    {cards.map((card) => (
                        <li key={card.title}>
                            <Link
                                href={card.href}
                                className="flex h-full flex-col gap-2 rounded-lg border p-4 hover:bg-muted"
                            >
                                <span className="flex items-center gap-2 font-semibold">
                                    <card.icon className="size-5" aria-hidden />
                                    {card.title}
                                </span>
                                <span>{card.text}</span>
                                <span className="text-sm text-muted-foreground">
                                    {card.note}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

MasterDataIndex.layout = {
    breadcrumbs: [{ title: 'Master data' }],
};
