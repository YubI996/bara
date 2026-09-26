import type { RuntimeField, RuntimeFile, RuntimeOption } from '@/types';

const numberFormat = (scale: number) =>
    new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: scale,
    });

export function isOptionList(value: unknown): value is RuntimeOption[] {
    return (
        Array.isArray(value) &&
        value.every((v) => typeof v === 'object' && v !== null && 'label' in v)
    );
}

export function isFileList(value: unknown): value is RuntimeFile[] {
    return (
        Array.isArray(value) &&
        value.every((v) => typeof v === 'object' && v !== null && 'url' in v)
    );
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024)
        return `${numberFormat(1).format(bytes / 1024)} KB`;
    return `${numberFormat(1).format(bytes / 1024 / 1024)} MB`;
}

function text(value: unknown): string {
    return typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
        ? String(value)
        : '';
}

/** Nilai sebagai teks untuk tabel dan halaman detail (format lokal Indonesia). */
export function formatValue(
    field: RuntimeField,
    value: unknown,
    timezone?: string,
): string {
    if (
        value === null ||
        value === undefined ||
        value === '' ||
        (Array.isArray(value) && value.length === 0)
    ) {
        return '—';
    }

    const label = (v: unknown) =>
        field.options.find((o) => o.value === v)?.label ?? text(v);

    switch (field.type) {
        case 'boolean':
            return value === true ? 'Ya' : 'Tidak';
        case 'enum':
            return label(value);
        case 'multi_enum':
            return Array.isArray(value)
                ? value.map(label).join(', ')
                : text(value);
        case 'money':
            return `Rp ${numberFormat(2).format(Number(value))}`;
        case 'percentage':
            return `${numberFormat(field.config.scale ?? 2).format(Number(value))}%`;
        case 'integer':
            return numberFormat(0).format(Number(value));
        case 'decimal':
            return numberFormat(field.config.scale ?? 2).format(Number(value));
        case 'date':
            return new Date(`${text(value)}T00:00:00`).toLocaleDateString(
                'id-ID',
                { dateStyle: 'medium' },
            );
        case 'datetime':
            return new Date(text(value)).toLocaleString('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: timezone,
            });
        case 'relationship':
            return isOptionList(value)
                ? value.map((v) => v.label).join(', ')
                : '—';
        default:
            return text(value) || '—';
    }
}

/** ISO UTC → nilai input datetime-local di zona Pemda. */
export function toLocalInput(iso: unknown, timezone: string): string {
    if (typeof iso !== 'string' || iso === '') return '';
    const parts = new Intl.DateTimeFormat('sv-SE', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(new Date(iso));
    return parts.replace(' ', 'T');
}
