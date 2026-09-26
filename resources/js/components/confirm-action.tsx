import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useRouterAction } from '@/hooks/use-router-action';

type Props = {
    /** Label tombol pemicu. */
    trigger: ReactNode;
    triggerVariant?: 'destructive' | 'default' | 'secondary' | 'outline';
    triggerClassName?: string;
    title: string;
    description: ReactNode;
    /** Isi tambahan di dialog, mis. ringkasan dampak. */
    children?: ReactNode;
    confirmLabel: string;
    processingLabel?: string;
    destructive?: boolean;
    method: 'post' | 'put' | 'patch' | 'delete';
    url: string;
    data?: Record<string, string | number | boolean | null>;
};

/**
 * Satu pola konfirmasi untuk semua aksi berdampak (UX-006, UX-009): dialog berjudul dengan
 * penjelasan dampak, tombol "Batal" + aksi eksplisit, status proses (UX-010), dan error server
 * ditampilkan di dalam dialog dengan role=alert (UX-001).
 */
export function ConfirmAction({
    trigger,
    triggerVariant = 'destructive',
    triggerClassName = 'min-h-11 md:min-h-9',
    title,
    description,
    children,
    confirmLabel,
    processingLabel = 'Memproses…',
    destructive = true,
    method,
    url,
    data,
}: Props) {
    const [open, setOpen] = useState(false);
    const { run, processing, errors, clearErrors } = useRouterAction();

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant={triggerVariant}
                    className={triggerClassName}
                >
                    {trigger}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                {children}
                {errors.length > 0 && (
                    <div
                        role="alert"
                        className="rounded-md border-2 border-red-700 bg-red-50 p-3 text-sm font-medium text-red-900 dark:border-red-400 dark:bg-red-950 dark:text-red-100"
                    >
                        {errors.map((message) => (
                            <p key={message}>{message}</p>
                        ))}
                    </div>
                )}
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="secondary"
                            className="min-h-11 md:min-h-9"
                        >
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={processing}
                        aria-disabled={processing}
                        className="min-h-11 md:min-h-9"
                        onClick={() =>
                            run(method, url, {
                                data,
                                toastErrors: false,
                                onSuccess: () => setOpen(false),
                            })
                        }
                    >
                        {processing ? processingLabel : confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
