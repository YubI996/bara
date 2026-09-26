import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const MESSAGES: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Akses ditolak',
        description:
            'Anda tidak memiliki izin untuk membuka halaman atau data ini. Bila Anda merasa seharusnya berhak, hubungi admin aplikasi di unit Anda.',
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description:
            'Halaman atau data yang Anda cari tidak ada, sudah dihapus, atau berada di luar kewenangan Anda.',
    },
    429: {
        title: 'Terlalu banyak permintaan',
        description: 'Tunggu sekitar satu menit, lalu coba lagi.',
    },
    500: {
        title: 'Terjadi kesalahan di server',
        description:
            'Permintaan Anda tidak dapat diproses. Kesalahan ini sudah tercatat. Coba lagi beberapa saat lagi; bila berulang, laporkan kode di bawah ke admin.',
    },
    503: {
        title: 'Layanan sedang dalam pemeliharaan',
        description: 'Silakan coba lagi beberapa saat lagi.',
    },
};

type Props = { status: number; trace_id?: string | null };

/** Halaman error Inertia berbahasa Indonesia dengan langkah pemulihan (UX-004). */
export default function ErrorPage({ status, trace_id }: Props) {
    const signedIn = usePage().props.auth?.user != null;
    const message = MESSAGES[status] ?? MESSAGES[500];

    return (
        <>
            <Head title={message.title} />
            <main
                id="main-content"
                className="flex min-h-svh items-center justify-center bg-background p-6"
            >
                <div className="max-w-lg space-y-4">
                    <p className="font-mono text-sm text-muted-foreground">
                        Kode {status}
                    </p>
                    <h1 className="text-2xl font-semibold">{message.title}</h1>
                    <p>{message.description}</p>
                    {trace_id && (
                        <p className="text-sm text-muted-foreground">
                            Kode pelacakan:{' '}
                            <span className="font-mono">{trace_id}</span>
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <Button
                            type="button"
                            variant="secondary"
                            className="min-h-11 md:min-h-9"
                            onClick={() => window.history.back()}
                        >
                            Kembali
                        </Button>
                        <Button asChild className="min-h-11 md:min-h-9">
                            <Link href={signedIn ? dashboard() : login()}>
                                {signedIn ? 'Ke dasbor' : 'Masuk'}
                            </Link>
                        </Button>
                    </div>
                </div>
            </main>
        </>
    );
}
