import { Check, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { RuntimeOption } from '@/types';

type Props = {
    /** Atribut dari <Field>: id, aria-describedby, aria-invalid, required. */
    aria: {
        id: string;
        'aria-describedby'?: string;
        'aria-invalid'?: true;
        required?: boolean;
    };
    name: string;
    label: string;
    lookupUrl: string;
    multiple: boolean;
    initial: RuntimeOption[];
};

const MIN_CHARS = 2;
const DEBOUNCE_MS = 300;

type Status = 'idle' | 'short' | 'loading' | 'done' | 'error';

/**
 * Pemilih data relasi dengan pencarian async (docs/06 §3, docs/11 §3.2): pola combobox ARIA 1.2
 * + listbox. Debounce 300 ms, minimal 2 karakter, jumlah hasil diumumkan lewat aria-live.
 * Keyboard: ↓/↑ pindah opsi, Enter memilih, Esc menutup daftar. Many-to-many menampilkan
 * pilihan sebagai daftar dengan tombol hapus per item.
 */
export function EntitySelector({
    aria,
    name,
    label,
    lookupUrl,
    multiple,
    initial,
}: Props) {
    const listboxId = useId();
    const statusId = useId();
    const [selected, setSelected] = useState<RuntimeOption[]>(initial);
    const [term, setTerm] = useState(multiple ? '' : (initial[0]?.label ?? ''));
    const [options, setOptions] = useState<RuntimeOption[]>([]);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const [status, setStatus] = useState<Status>('idle');
    const inputRef = useRef<HTMLInputElement>(null);
    const abort = useRef<AbortController | null>(null);

    // Pencarian ber-debounce; permintaan lama dibatalkan agar hasil tidak tertukar.
    useEffect(() => {
        if (!open) return;
        const q = term.trim();
        if (q.length < MIN_CHARS) {
            setOptions([]);
            setStatus('short');
            return;
        }

        const timer = setTimeout(async () => {
            abort.current?.abort();
            const controller = new AbortController();
            abort.current = controller;
            setStatus('loading');
            try {
                const response = await fetch(
                    `${lookupUrl}?q=${encodeURIComponent(q)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    },
                );
                if (!response.ok) throw new Error(String(response.status));
                const body: unknown = await response.json();
                const list =
                    typeof body === 'object' &&
                    body !== null &&
                    'options' in body &&
                    Array.isArray(body.options)
                        ? body.options.filter(
                              (o: unknown): o is RuntimeOption =>
                                  typeof o === 'object' &&
                                  o !== null &&
                                  'value' in o &&
                                  'label' in o &&
                                  typeof o.value === 'string' &&
                                  typeof o.label === 'string',
                          )
                        : [];
                setOptions(list);
                setActive(list.length > 0 ? 0 : -1);
                setStatus('done');
            } catch (error) {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    setStatus('error');
                }
            }
        }, DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [term, open, lookupUrl]);

    useEffect(() => () => abort.current?.abort(), []);

    const isSelected = (o: RuntimeOption) =>
        selected.some((s) => s.value === o.value);

    const choose = (option: RuntimeOption) => {
        if (multiple) {
            setSelected((prev) =>
                prev.some((s) => s.value === option.value)
                    ? prev.filter((s) => s.value !== option.value)
                    : [...prev, option],
            );
            setTerm('');
        } else {
            setSelected([option]);
            setTerm(option.label);
            setOpen(false);
        }
        setOptions((o) => (multiple ? o : []));
        inputRef.current?.focus();
    };

    const clearSingle = () => {
        setSelected([]);
        setTerm('');
        inputRef.current?.focus();
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                setOpen(true);
                setActive((i) => Math.min(options.length - 1, i + 1));
                break;
            case 'ArrowUp':
                event.preventDefault();
                setActive((i) => Math.max(0, i - 1));
                break;
            case 'Enter':
                if (open && active >= 0 && options[active]) {
                    event.preventDefault();
                    choose(options[active]);
                }
                break;
            case 'Escape':
                if (open) {
                    event.preventDefault();
                    setOpen(false);
                    if (!multiple) setTerm(selected[0]?.label ?? '');
                }
                break;
        }
    };

    const message = (() => {
        if (!open) return '';
        switch (status) {
            case 'short':
                return `Ketik minimal ${MIN_CHARS} huruf untuk mencari.`;
            case 'loading':
                return 'Mencari…';
            case 'error':
                return 'Pencarian gagal. Coba lagi.';
            case 'done':
                return options.length === 0
                    ? 'Tidak ada data yang cocok.'
                    : `${options.length} hasil. Gunakan panah atas/bawah, lalu Enter untuk memilih.`;
            default:
                return '';
        }
    })();

    const describedBy =
        [aria['aria-describedby'], statusId].filter(Boolean).join(' ') ||
        undefined;

    return (
        <div className="grid gap-2">
            {multiple && selected.length > 0 && (
                <ul
                    aria-label={`${label} terpilih`}
                    className="flex flex-wrap gap-2"
                >
                    {selected.map((o) => (
                        <li
                            key={o.value}
                            className="inline-flex min-h-11 items-center gap-1 rounded-md border border-input bg-muted py-1 pr-1 pl-3 text-sm md:min-h-9"
                        >
                            {o.label}
                            <button
                                type="button"
                                onClick={() => choose(o)}
                                aria-label={`Hapus ${o.label}`}
                                className="inline-flex size-9 items-center justify-center rounded-md hover:bg-background md:size-7"
                            >
                                <X className="size-4" aria-hidden />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {/* Nilai yang dikirim; kosong berarti relasi dikosongkan. */}
            {multiple ? (
                selected.map((o) => (
                    <input
                        key={o.value}
                        type="hidden"
                        name={`${name}[]`}
                        value={o.value}
                    />
                ))
            ) : (
                <input
                    type="hidden"
                    name={name}
                    value={selected[0]?.value ?? ''}
                />
            )}

            <div className="relative flex max-w-xl items-center gap-2">
                <Input
                    ref={inputRef}
                    id={aria.id}
                    type="text"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded={open && options.length > 0}
                    aria-controls={listboxId}
                    aria-activedescendant={
                        open && active >= 0 && options[active]
                            ? `${listboxId}-${active}`
                            : undefined
                    }
                    aria-describedby={describedBy}
                    aria-invalid={aria['aria-invalid']}
                    aria-required={aria.required || undefined}
                    autoComplete="off"
                    value={term}
                    placeholder={multiple ? 'Cari untuk menambah…' : 'Cari…'}
                    onChange={(e) => {
                        setTerm(e.target.value);
                        setOpen(true);
                        if (!multiple && selected.length > 0) setSelected([]);
                    }}
                    onFocus={() => setOpen(true)}
                    onBlur={() =>
                        // Beri waktu klik opsi sebelum daftar ditutup.
                        setTimeout(() => {
                            setOpen(false);
                            if (!multiple) setTerm(selected[0]?.label ?? '');
                        }, 150)
                    }
                    onKeyDown={onKeyDown}
                    className="h-11 md:h-9"
                />
                {!multiple && selected.length > 0 && !aria.required && (
                    <Button
                        type="button"
                        variant="secondary"
                        className="min-h-11 md:min-h-9"
                        onClick={clearSingle}
                    >
                        Kosongkan
                        <span className="sr-only"> {label}</span>
                    </Button>
                )}

                <ul
                    id={listboxId}
                    role="listbox"
                    aria-label={`Hasil pencarian ${label}`}
                    aria-multiselectable={multiple || undefined}
                    hidden={!open || options.length === 0}
                    className="absolute top-full left-0 z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-md border border-input bg-popover p-1 text-popover-foreground shadow-md"
                >
                    {options.map((o, index) => (
                        <li
                            key={o.value}
                            id={`${listboxId}-${index}`}
                            role="option"
                            aria-selected={isSelected(o)}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => choose(o)}
                            onMouseEnter={() => setActive(index)}
                            className={cn(
                                'flex min-h-11 cursor-pointer items-center gap-2 rounded-sm px-2 text-sm md:min-h-9',
                                index === active &&
                                    'bg-accent text-accent-foreground outline-2 outline-offset-[-2px] outline-[var(--focus)]',
                            )}
                        >
                            <Check
                                aria-hidden
                                className={cn(
                                    'size-4 shrink-0',
                                    isSelected(o) ? 'opacity-100' : 'opacity-0',
                                )}
                            />
                            {o.label}
                        </li>
                    ))}
                </ul>
            </div>

            <p
                id={statusId}
                role="status"
                aria-live="polite"
                className="text-sm text-muted-foreground"
            >
                {message}
            </p>
        </div>
    );
}
