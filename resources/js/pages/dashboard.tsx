import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { useMainNav } from '@/hooks/use-main-nav';
import { dashboard } from '@/routes';

const DESCRIPTIONS: Record<string, string> = {
    Data: 'Isi dan kelola data aplikasi yang menjadi kewenangan unit Anda.',
    Organisasi: 'Kelola struktur unit organisasi Pemda.',
    Aplikasi: 'Rancang aplikasi, entity, dan field tanpa menulis kode.',
};

/**
 * Halaman pertama setelah masuk. Sampai modul dasbor indikator (M9) tersedia, halaman ini
 * menjadi pintasan ke menu yang boleh diakses pengguna (UX-005).
 */
export default function Dashboard() {
    const { auth, pemda } = usePage().props;
    const shortcuts = useMainNav().filter((item) => item.title !== 'Dasbor');

    return (
        <>
            <Head title="Dasbor" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Dasbor"
                    description={`Selamat datang, ${auth.user.name}. ${pemda.name}.`}
                />
                <nav aria-label="Pintasan">
                    <ul className="grid gap-4 md:grid-cols-3">
                        {shortcuts.map((item) => (
                            <li key={item.title}>
                                <Link
                                    href={item.href}
                                    className="flex h-full flex-col gap-2 rounded-lg border p-4 hover:bg-muted"
                                >
                                    <span className="flex items-center gap-2 font-semibold">
                                        {item.icon && (
                                            <item.icon
                                                className="size-5"
                                                aria-hidden
                                            />
                                        )}
                                        {item.title}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {DESCRIPTIONS[item.title] ?? ''}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dasbor', href: dashboard() }],
};
