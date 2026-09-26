import type { ReactNode } from 'react';

/**
 * Pesan status sukses (mis. "Tautan reset terkirim"). role=status agar diumumkan pembaca layar
 * tanpa memindah fokus (WCAG 4.1.3); warna hijau-900 di atas hijau-50 ≥ 7:1.
 */
export function StatusMessage({ children }: { children: ReactNode }) {
    return (
        <p
            role="status"
            className="rounded-md border border-green-700 bg-green-50 px-3 py-2 text-sm font-medium text-green-900 dark:border-green-400 dark:bg-green-950 dark:text-green-100"
        >
            {children}
        </p>
    );
}
