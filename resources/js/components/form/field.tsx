import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    /** Render input; terima atribut ARIA yang sudah dihubungkan ke hint & error. */
    children: (aria: {
        id: string;
        'aria-describedby'?: string;
        'aria-invalid'?: true;
        required?: boolean;
    }) => ReactNode;
};

/**
 * Pembungkus field form yang aksesibel: label terhubung, penanda wajib berupa teks
 * (bukan hanya warna/asterisk), hint & error dihubungkan lewat aria-describedby.
 */
export function Field({ id, label, required, hint, error, children }: Props) {
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy =
        [hintId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {required ? (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        (wajib)
                    </span>
                ) : (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        (opsional)
                    </span>
                )}
            </Label>
            {hint && (
                <p id={hintId} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            {children({
                id,
                'aria-describedby': describedBy,
                'aria-invalid': error ? true : undefined,
                required,
            })}
            {error && (
                <p
                    id={errorId}
                    className="text-sm font-medium text-red-700 dark:text-red-300"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
