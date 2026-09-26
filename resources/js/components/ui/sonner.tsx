import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            // Toast bertumpuk dibuat transparan oleh sonner sehingga gagal kontras (WCAG 1.4.3):
            // tampilkan terbentang dan beri waktu baca yang cukup (WCAG 2.2.1).
            expand
            visibleToasts={3}
            duration={8000}
            closeButton
            toastOptions={{ closeButtonAriaLabel: 'Tutup notifikasi' }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
