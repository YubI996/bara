import { fieldId } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { Textarea } from '@/components/form/textarea';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { RuntimeField, RuntimeFile } from '@/types';
import { formatBytes, isFileList, isOptionList, toLocalInput } from './format';

type Errors = Partial<Record<string, string>>;

type Props = {
    field: RuntimeField;
    value: unknown;
    errors: Errors;
    timezone: string;
};

const inputClass = 'h-11 md:h-9';

function str(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

function errorFor(errors: Errors, code: string): string | undefined {
    return (
        errors[`data.${code}`] ??
        Object.entries(errors).find(
            ([k]) =>
                k.startsWith(`data.${code}.`) || k.startsWith(`files.${code}.`),
        )?.[1]
    );
}

function numberHint(field: RuntimeField): string {
    const parts: string[] = [];
    if (field.config.min !== undefined && field.type !== 'percentage')
        parts.push(`minimal ${field.config.min}`);
    if (field.config.max !== undefined && field.type !== 'percentage')
        parts.push(`maksimal ${field.config.max}`);
    if (field.type !== 'integer')
        parts.push('gunakan koma untuk desimal, mis. 1.500.000,50');
    return parts.join('; ');
}

function hint(field: RuntimeField, extra?: string): string | undefined {
    const text = [field.help_text, extra].filter(Boolean).join(' ');
    return text === '' ? undefined : text;
}

/** Satu field form runtime; komponen dipilih dari FieldType::uiComponent (docs/11 §3.2). */
export function FieldInput({ field, value, errors, timezone }: Props) {
    const name = `data[${field.code}]`;
    const id = fieldId(`data.${field.code}`);
    const error = errorFor(errors, field.code);
    const defaultValue = value === undefined ? field.default : value;

    if (field.deferred) {
        return (
            <div className="grid gap-1 rounded-md border border-dashed p-3">
                <p className="text-sm font-medium">{field.label}</p>
                <p className="text-sm text-muted-foreground">
                    Pilihan wilayah tersedia setelah data wilayah Kemendagri
                    dimuat (M4). Field ini dilewati untuk sementara.
                </p>
            </div>
        );
    }

    if (field.access === 'masked') {
        return (
            <div className="grid gap-1">
                <p className="text-sm font-medium">{field.label}</p>
                <p className="font-mono">{str(value) || '—'}</p>
                <p className="text-sm text-muted-foreground">
                    Disamarkan: {field.classification_label}. Anda tidak
                    berwenang mengubahnya.
                </p>
            </div>
        );
    }

    switch (field.component) {
        case 'Checkbox':
            return (
                <div className="grid gap-1">
                    <input type="hidden" name={name} value="0" />
                    <div className="flex items-start gap-3">
                        <input
                            id={id}
                            name={name}
                            type="checkbox"
                            value="1"
                            defaultChecked={defaultValue === true}
                            aria-describedby={
                                [
                                    field.help_text ? `${id}-hint` : '',
                                    error ? `${id}-error` : '',
                                ]
                                    .filter(Boolean)
                                    .join(' ') || undefined
                            }
                            aria-invalid={error ? true : undefined}
                            className="mt-0.5 size-6 shrink-0 accent-primary"
                        />
                        <label
                            htmlFor={id}
                            className="text-sm leading-6 font-medium"
                        >
                            {field.label}
                            {field.required && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    (wajib)
                                </span>
                            )}
                        </label>
                    </div>
                    {field.help_text && (
                        <p
                            id={`${id}-hint`}
                            className="text-sm text-muted-foreground"
                        >
                            {field.help_text}
                        </p>
                    )}
                    {error && (
                        <p
                            id={`${id}-error`}
                            className="text-sm font-medium text-red-700 dark:text-red-300"
                        >
                            {error}
                        </p>
                    )}
                </div>
            );
        case 'RadioGroup':
        case 'CheckboxGroup': {
            const multiple = field.component === 'CheckboxGroup';
            const selected = Array.isArray(defaultValue)
                ? defaultValue.map(String)
                : [str(defaultValue)];
            return (
                <fieldset
                    id={id}
                    tabIndex={-1}
                    className="grid gap-2"
                    aria-describedby={
                        [
                            field.help_text ? `${id}-hint` : '',
                            error ? `${id}-error` : '',
                        ]
                            .filter(Boolean)
                            .join(' ') || undefined
                    }
                    aria-invalid={error ? true : undefined}
                >
                    <legend className="text-sm font-medium">
                        {field.label}
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            ({field.required ? 'wajib' : 'opsional'})
                        </span>
                    </legend>
                    {field.help_text && (
                        <p
                            id={`${id}-hint`}
                            className="text-sm text-muted-foreground"
                        >
                            {field.help_text}
                        </p>
                    )}
                    <div className="grid gap-1 sm:grid-cols-2">
                        {field.options.map((option) => (
                            <label
                                key={option.value}
                                className="flex min-h-11 items-center gap-3 md:min-h-9"
                            >
                                <input
                                    type={multiple ? 'checkbox' : 'radio'}
                                    name={multiple ? `${name}[]` : name}
                                    value={option.value}
                                    defaultChecked={selected.includes(
                                        option.value,
                                    )}
                                    className="size-6 shrink-0 accent-primary"
                                />
                                <span>{option.label}</span>
                            </label>
                        ))}
                    </div>
                    {error && (
                        <p
                            id={`${id}-error`}
                            className="text-sm font-medium text-red-700 dark:text-red-300"
                        >
                            {error}
                        </p>
                    )}
                </fieldset>
            );
        }
        case 'FileUpload':
            return (
                <FileUpload field={field} value={value} error={error} id={id} />
            );
        default:
            break;
    }

    return (
        <Field
            id={id}
            label={field.label}
            required={field.required}
            hint={hint(
                field,
                ['NumberInput', 'MoneyInput', 'PercentInput'].includes(
                    field.component,
                )
                    ? numberHint(field)
                    : field.component === 'RichText'
                      ? 'Format dasar HTML diizinkan: paragraf, tebal, miring, daftar, tautan https.'
                      : field.config.max_length
                        ? `Maksimal ${field.config.max_length} karakter.`
                        : undefined,
            )}
            error={error}
        >
            {(aria) => {
                switch (field.component) {
                    case 'Textarea':
                    case 'RichText':
                        return (
                            <Textarea
                                {...aria}
                                name={name}
                                defaultValue={str(defaultValue)}
                                maxLength={field.config.max_length}
                                rows={field.component === 'RichText' ? 8 : 4}
                            />
                        );
                    case 'NumberInput':
                    case 'MoneyInput':
                    case 'PercentInput': {
                        const prefix =
                            field.component === 'MoneyInput' ? 'Rp' : undefined;
                        const suffix =
                            field.component === 'PercentInput'
                                ? '%'
                                : undefined;
                        return (
                            <div className="flex items-center gap-2">
                                {prefix && (
                                    <span
                                        aria-hidden="true"
                                        className="text-muted-foreground"
                                    >
                                        {prefix}
                                    </span>
                                )}
                                <Input
                                    {...aria}
                                    name={name}
                                    inputMode={
                                        field.type === 'integer'
                                            ? 'numeric'
                                            : 'decimal'
                                    }
                                    defaultValue={str(defaultValue)}
                                    className={cn(inputClass, 'max-w-xs')}
                                    autoComplete="off"
                                />
                                {suffix && (
                                    <span
                                        aria-hidden="true"
                                        className="text-muted-foreground"
                                    >
                                        {suffix}
                                    </span>
                                )}
                            </div>
                        );
                    }
                    case 'DateInput':
                        return (
                            <Input
                                {...aria}
                                type="date"
                                name={name}
                                defaultValue={str(defaultValue)}
                                className={cn(inputClass, 'max-w-xs')}
                            />
                        );
                    case 'DateTimeInput':
                        return (
                            <Input
                                {...aria}
                                type="datetime-local"
                                name={name}
                                defaultValue={toLocalInput(
                                    defaultValue,
                                    timezone,
                                )}
                                className={cn(inputClass, 'max-w-xs')}
                            />
                        );
                    case 'Select':
                        return (
                            <NativeSelect
                                {...aria}
                                name={name}
                                defaultValue={str(defaultValue)}
                            >
                                <option value="">
                                    {field.required
                                        ? 'Pilih salah satu'
                                        : 'Tidak ada'}
                                </option>
                                {field.options.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        );
                    case 'EntitySelector': {
                        const many =
                            field.config.cardinality === 'many_to_many';
                        const selected = isOptionList(defaultValue)
                            ? defaultValue.map((o) => o.value)
                            : [];
                        return (
                            <NativeSelect
                                {...aria}
                                name={many ? `${name}[]` : name}
                                multiple={many}
                                defaultValue={
                                    many ? selected : (selected[0] ?? '')
                                }
                                className={many ? 'h-auto min-h-32' : undefined}
                            >
                                {!many && (
                                    <option value="">
                                        {field.required
                                            ? 'Pilih data'
                                            : 'Tidak ada'}
                                    </option>
                                )}
                                {field.options.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        );
                    }
                    default:
                        return (
                            <Input
                                {...aria}
                                name={name}
                                defaultValue={str(defaultValue)}
                                maxLength={field.config.max_length}
                                className={inputClass}
                            />
                        );
                }
            }}
        </Field>
    );
}

function FileUpload({
    field,
    value,
    error,
    id,
}: {
    field: RuntimeField;
    value: unknown;
    error?: string;
    id: string;
}) {
    const files: RuntimeFile[] = isFileList(value) ? value : [];
    const mimes = field.config.mimes ?? [];
    const maxFiles = field.config.max_files ?? 1;

    return (
        <fieldset
            id={id}
            tabIndex={-1}
            className="grid gap-2 rounded-md border p-3"
            aria-describedby={`${id}-hint${error ? ` ${id}-error` : ''}`}
        >
            <legend className="px-1 text-sm font-medium">
                {field.label}
                <span className="font-normal text-muted-foreground">
                    {' '}
                    ({field.required ? 'wajib' : 'opsional'})
                </span>
            </legend>
            <p id={`${id}-hint`} className="text-sm text-muted-foreground">
                {[
                    field.help_text,
                    `Jenis: ${mimes.join(', ')}. Maksimal ${maxFiles} berkas, masing-masing ${formatBytes((field.config.max_kb ?? 5120) * 1024)}.`,
                ]
                    .filter(Boolean)
                    .join(' ')}
            </p>
            {files.length > 0 && (
                <ul className="grid gap-1">
                    {files.map((file) => (
                        <li key={file.id}>
                            <label className="flex min-h-11 items-center gap-3 md:min-h-9">
                                <input
                                    type="checkbox"
                                    name={`data[${field.code}][]`}
                                    value={file.id}
                                    defaultChecked
                                    className="size-6 accent-primary"
                                />
                                <span>
                                    Pertahankan {file.name} (
                                    {formatBytes(file.size)})
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
            )}
            <label htmlFor={`${id}_upload`} className="text-sm font-medium">
                {files.length > 0 ? 'Tambah berkas' : 'Pilih berkas'}
            </label>
            <input
                id={`${id}_upload`}
                type="file"
                name={`files[${field.code}][]`}
                multiple={maxFiles > 1}
                accept={mimes.map((m) => `.${m}`).join(',')}
                className="text-sm file:mr-3 file:min-h-11 file:rounded-md file:border file:bg-secondary file:px-3 file:font-medium md:file:min-h-9"
            />
            {error && (
                <p
                    id={`${id}-error`}
                    className="text-sm font-medium text-red-700 dark:text-red-300"
                >
                    {error}
                </p>
            )}
        </fieldset>
    );
}
