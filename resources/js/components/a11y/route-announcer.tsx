import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Pengumuman navigasi Inertia untuk pembaca layar (A11Y-009, docs/11 §3.1):
 * - pindah halaman (path berubah): umumkan judul halaman dan pindahkan fokus ke konten utama;
 * - permintaan lebih dari 1 detik: umumkan "Memuat…".
 * Kunjungan yang tetap di path yang sama (filter, submit form berisi error) tidak memindah
 * fokus agar ringkasan error tetap memegang fokus.
 */
export function RouteAnnouncer() {
    const [message, setMessage] = useState('');
    const lastPath = useRef(
        typeof window === 'undefined' ? '' : window.location.pathname,
    );

    useEffect(() => {
        let slowTimer: ReturnType<typeof setTimeout> | undefined;

        const offStart = router.on('start', () => {
            clearTimeout(slowTimer);
            slowTimer = setTimeout(() => setMessage('Memuat…'), 1000);
        });
        const offFinish = router.on('finish', () => clearTimeout(slowTimer));
        const offNavigate = router.on('navigate', () => {
            clearTimeout(slowTimer);
            const path = window.location.pathname;
            if (path === lastPath.current) {
                setMessage('');
                return;
            }
            lastPath.current = path;

            // Tunggu <Head> memperbarui document.title dan konten baru dirender.
            requestAnimationFrame(() => {
                setMessage(`Halaman dimuat: ${document.title}`);
                const main = document.getElementById('main-content');
                const heading = main?.querySelector<HTMLElement>('h1');
                const target = heading ?? main;
                if (target) {
                    if (!target.hasAttribute('tabindex')) {
                        target.setAttribute('tabindex', '-1');
                    }
                    target.focus({ preventScroll: true });
                }
            });
        });

        return () => {
            clearTimeout(slowTimer);
            offStart();
            offFinish();
            offNavigate();
        };
    }, []);

    return (
        <div
            role="status"
            aria-live="polite"
            aria-atomic="true"
            className="sr-only"
        >
            {message}
        </div>
    );
}
