import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { login, logout } from '@/routes';

const WARN_BEFORE_MS = 2 * 60 * 1000;

/**
 * Peringatan sebelum sesi habis (WCAG 2.2.1, A11Y-010): muncul 2 menit sebelum batas idle dari
 * server (`session.idle_minutes`), dengan pilihan memperpanjang. Setiap permintaan Inertia
 * mereset hitungan karena server juga mencatat aktivitas di setiap permintaan.
 */
export function SessionTimeoutWarning() {
    const idleMinutes = usePage().props.session?.idle_minutes ?? null;
    const [phase, setPhase] = useState<'idle' | 'warning' | 'expired'>('idle');
    const [remaining, setRemaining] = useState(0);
    const deadline = useRef(0);

    const restart = useCallback(() => {
        if (idleMinutes === null) return;
        deadline.current = Date.now() + idleMinutes * 60 * 1000;
        setPhase('idle');
    }, [idleMinutes]);

    useEffect(() => {
        restart();
        return router.on('finish', restart);
    }, [restart]);

    useEffect(() => {
        if (idleMinutes === null) return;
        const tick = setInterval(() => {
            const left = deadline.current - Date.now();
            setRemaining(Math.max(0, Math.ceil(left / 1000)));
            if (left <= 0) {
                setPhase('expired');
            } else if (left <= WARN_BEFORE_MS) {
                setPhase((p) => (p === 'idle' ? 'warning' : p));
            }
        }, 1000);
        return () => clearInterval(tick);
    }, [idleMinutes]);

    if (idleMinutes === null || phase === 'idle') return null;

    const minutes = Math.floor(remaining / 60);
    const seconds = String(remaining % 60).padStart(2, '0');

    return (
        <Dialog open onOpenChange={() => undefined}>
            <DialogContent
                role="alertdialog"
                aria-describedby="session-timeout-description"
            >
                {phase === 'warning' ? (
                    <>
                        <DialogTitle>Sesi Anda akan berakhir</DialogTitle>
                        <DialogDescription id="session-timeout-description">
                            Tidak ada aktivitas selama hampir {idleMinutes}{' '}
                            menit. Sesi berakhir dalam {minutes}:{seconds}.
                            Isian yang belum disimpan akan hilang.
                        </DialogDescription>
                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                className="min-h-11 md:min-h-9"
                                onClick={() => router.post(logout.url())}
                            >
                                Keluar sekarang
                            </Button>
                            <Button
                                type="button"
                                className="min-h-11 md:min-h-9"
                                onClick={() =>
                                    router.reload({
                                        only: ['session'],
                                        onFinish: restart,
                                    })
                                }
                            >
                                Perpanjang sesi
                            </Button>
                        </DialogFooter>
                    </>
                ) : (
                    <>
                        <DialogTitle>Sesi Anda sudah berakhir</DialogTitle>
                        <DialogDescription id="session-timeout-description">
                            Demi keamanan, Anda perlu masuk kembali. Isian yang
                            belum disimpan tidak dapat dikirim.
                        </DialogDescription>
                        <DialogFooter>
                            <Button asChild className="min-h-11 md:min-h-9">
                                <a href={login.url()}>Masuk kembali</a>
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
