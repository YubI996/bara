import type { ReactNode } from 'react';

type Props = {
    id: string;
    name: string;
    label: ReactNode;
    hint?: string;
    defaultChecked?: boolean;
    checked?: boolean;
    onChange?: (checked: boolean) => void;
    disabled?: boolean;
    error?: string;
};

/**
 * Checkbox asli bernilai "1" (lolos aturan `boolean` Laravel) dengan target sentuh ≥ 24px
 * dan label yang bisa diklik (WCAG 2.5.8).
 */
export function CheckboxField({
    id,
    name,
    label,
    hint,
    defaultChecked,
    checked,
    onChange,
    disabled,
    error,
}: Props) {
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy =
        [hintId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex items-start gap-3">
            <input
                id={id}
                name={name}
                type="checkbox"
                value="1"
                defaultChecked={
                    checked === undefined ? defaultChecked : undefined
                }
                checked={checked}
                onChange={(e) => onChange?.(e.target.checked)}
                disabled={disabled}
                aria-describedby={describedBy}
                aria-invalid={error ? true : undefined}
                className="mt-0.5 size-6 shrink-0 accent-primary disabled:opacity-50"
            />
            <div className="grid gap-1">
                <label htmlFor={id} className="text-sm leading-6 font-medium">
                    {label}
                </label>
                {hint && (
                    <p id={hintId} className="text-sm text-muted-foreground">
                        {hint}
                    </p>
                )}
                {error && (
                    <p
                        id={errorId}
                        className="text-sm font-medium text-red-700 dark:text-red-300"
                    >
                        {error}
                    </p>
                )}
            </div>
        </div>
    );
}
