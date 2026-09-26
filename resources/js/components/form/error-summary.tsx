import { useEffect, useRef } from 'react';

/** Kunci error Laravel (config.options.0.value) → id elemen (config_options_0_value). */
export function fieldId(key: string): string {
    return key.replace(/\./g, '_');
}

type Props = {
    errors: Partial<Record<string, string>>;
    /** Label field untuk tautan di ringkasan. Kunci = nama field = id input. */
    labels: Record<string, string>;
};

/**
 * Ringkasan error di atas form (WCAG 3.3.1, 3.3.3): fokus dipindah ke ringkasan,
 * tiap error menaut ke field-nya.
 */
export function ErrorSummary({ errors, labels }: Props) {
    const ref = useRef<HTMLDivElement>(null);
    const entries = Object.entries(errors).filter(
        (entry): entry is [string, string] => typeof entry[1] === 'string',
    );
    const signature = entries.map(([key, message]) => key + message).join('|');

    useEffect(() => {
        if (signature !== '') {
            ref.current?.focus();
        }
    }, [signature]);

    if (entries.length === 0) {
        return null;
    }

    return (
        <div
            ref={ref}
            tabIndex={-1}
            role="alert"
            aria-labelledby="error-summary-title"
            className="rounded-md border-2 border-red-700 bg-red-50 p-4 text-red-900 focus:outline-none focus-visible:ring-4 focus-visible:ring-red-700/40 dark:border-red-400 dark:bg-red-950 dark:text-red-100"
        >
            <h2 id="error-summary-title" className="font-semibold">
                Periksa kembali {entries.length} isian berikut:
            </h2>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {entries.map(([field, message]) => (
                    <li key={field}>
                        <a
                            href={`#${fieldId(field)}`}
                            className="underline underline-offset-4"
                        >
                            {labels[field] ??
                                labels[
                                    field.split('.').slice(0, 2).join('.')
                                ] ??
                                field}
                            : {message}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}
