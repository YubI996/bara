import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

type Method = 'post' | 'put' | 'patch' | 'delete';

type Options = {
    data?: Record<string, string | number | boolean | null>;
    onSuccess?: () => void;
    /** Tampilkan error sebagai toast (default). false bila pemanggil merender error sendiri. */
    toastErrors?: boolean;
};

/**
 * Aksi router di luar <Form> (tombol naik/turun, buat draft, hapus). Melacak status proses
 * supaya tombol bisa dinonaktifkan (cegah klik ganda) dan menampilkan error validasi server
 * yang tidak punya field di halaman (UX-001, UX-010).
 */
export function useRouterAction() {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);

    const run = (method: Method, url: string, options: Options = {}) => {
        if (processing) {
            return;
        }

        router.visit(url, {
            method,
            data: options.data ?? {},
            preserveScroll: true,
            // Pertahankan state komponen (mis. dialog tetap terbuka) bila server mengembalikan error.
            preserveState: 'errors',
            onStart: () => {
                setProcessing(true);
                setErrors([]);
            },
            onFinish: () => setProcessing(false),
            onSuccess: () => options.onSuccess?.(),
            onError: (bag) => {
                const messages = Object.values(bag).filter(
                    (m): m is string => typeof m === 'string' && m !== '',
                );
                setErrors(messages);
                if (options.toastErrors !== false) {
                    messages.forEach((message) => toast.error(message));
                }
            },
        });
    };

    return { run, processing, errors, clearErrors: () => setErrors([]) };
}
