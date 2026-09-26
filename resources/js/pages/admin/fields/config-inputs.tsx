import { useState } from 'react';
import { fieldId } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { FieldConfig, Option } from '@/types';

type Errors = Partial<Record<string, string>>;

type Props = {
    type: string;
    config: FieldConfig;
    errors: Errors;
    targets: Option[];
    fileExtensions: string[];
    supportsDefault: boolean;
};

function str(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

/** Input angka/teks sederhana untuk satu kunci config. */
function ConfigInput({
    name,
    label,
    hint,
    config,
    errors,
    inputMode,
    mono,
}: {
    name: string;
    label: string;
    hint?: string;
    config: FieldConfig;
    errors: Errors;
    inputMode?: 'numeric' | 'decimal' | 'text';
    mono?: boolean;
}) {
    const key = `config.${name}`;
    return (
        <Field id={fieldId(key)} label={label} hint={hint} error={errors[key]}>
            {(aria) => (
                <Input
                    {...aria}
                    name={`config[${name}]`}
                    defaultValue={str(config[name])}
                    inputMode={inputMode}
                    spellCheck={false}
                    className={mono ? 'h-11 font-mono md:h-9' : 'h-11 md:h-9'}
                />
            )}
        </Field>
    );
}

type OptionRow = { value: string; label: string };

function OptionsEditor({
    config,
    errors,
}: {
    config: FieldConfig;
    errors: Errors;
}) {
    const initial = Array.isArray(config.options)
        ? (config.options as OptionRow[])
        : [{ value: '', label: '' }];
    const [rows, setRows] = useState<OptionRow[]>(
        initial.length ? initial : [{ value: '', label: '' }],
    );

    return (
        <fieldset className="space-y-3 rounded-md border p-4">
            <legend className="px-1 font-medium">Opsi pilihan (wajib)</legend>
            <p className="text-sm text-muted-foreground" id="options-hint">
                Nilai disimpan di data (huruf kecil, angka, garis bawah). Label
                ditampilkan ke pengguna.
            </p>
            {errors['config.options'] && (
                <p
                    id="config_options"
                    className="text-sm font-medium text-red-700 dark:text-red-300"
                >
                    {errors['config.options']}
                </p>
            )}
            <ol className="space-y-3">
                {rows.map((row, i) => (
                    <li
                        key={i}
                        className="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                    >
                        <Field
                            id={`config_options_${i}_value`}
                            label={`Nilai opsi ${i + 1}`}
                            error={errors[`config.options.${i}.value`]}
                        >
                            {(aria) => (
                                <Input
                                    {...aria}
                                    name={`config[options][${i}][value]`}
                                    defaultValue={row.value}
                                    spellCheck={false}
                                    className="h-11 font-mono md:h-9"
                                />
                            )}
                        </Field>
                        <Field
                            id={`config_options_${i}_label`}
                            label={`Label opsi ${i + 1}`}
                            error={errors[`config.options.${i}.label`]}
                        >
                            {(aria) => (
                                <Input
                                    {...aria}
                                    name={`config[options][${i}][label]`}
                                    defaultValue={row.label}
                                    className="h-11 md:h-9"
                                />
                            )}
                        </Field>
                        <Button
                            type="button"
                            variant="ghost"
                            className="min-h-11 md:min-h-9"
                            disabled={rows.length === 1}
                            onClick={() =>
                                setRows(rows.filter((_, j) => j !== i))
                            }
                        >
                            Hapus<span className="sr-only"> opsi {i + 1}</span>
                        </Button>
                    </li>
                ))}
            </ol>
            <Button
                type="button"
                variant="secondary"
                className="min-h-11 md:min-h-9"
                onClick={() => setRows([...rows, { value: '', label: '' }])}
            >
                Tambah opsi
            </Button>
        </fieldset>
    );
}

export function ConfigInputs({
    type,
    config,
    errors,
    targets,
    fileExtensions,
    supportsDefault,
}: Props) {
    const defaultInput = supportsDefault && type !== 'boolean' && (
        <ConfigInput
            name="default"
            label="Nilai default"
            hint="Diisikan otomatis pada data baru. Wajib bila field wajib ditambahkan ke entity yang sudah punya data."
            config={config}
            errors={errors}
            mono={type === 'enum'}
        />
    );

    switch (type) {
        case 'string':
            return (
                <>
                    <ConfigInput
                        name="max_length"
                        label="Panjang maksimum"
                        hint="1–500 karakter. Default 255."
                        config={config}
                        errors={errors}
                        inputMode="numeric"
                    />
                    <ConfigInput
                        name="pattern"
                        label="Pola (regex)"
                        hint="Opsional. Contoh NIK: [0-9]{16}. Pola bersarang seperti (a+)+ ditolak demi keamanan server."
                        config={config}
                        errors={errors}
                        mono
                    />
                    {defaultInput}
                </>
            );
        case 'text':
        case 'rich_text':
            return (
                <>
                    <ConfigInput
                        name="max_length"
                        label="Panjang maksimum"
                        config={config}
                        errors={errors}
                        inputMode="numeric"
                    />
                    {defaultInput}
                </>
            );
        case 'integer':
        case 'decimal':
            return (
                <>
                    {type === 'decimal' && (
                        <ConfigInput
                            name="scale"
                            label="Jumlah angka desimal"
                            hint="0–6. Default 2."
                            config={config}
                            errors={errors}
                            inputMode="numeric"
                        />
                    )}
                    <ConfigInput
                        name="min"
                        label="Nilai minimum"
                        config={config}
                        errors={errors}
                        inputMode="decimal"
                    />
                    <ConfigInput
                        name="max"
                        label="Nilai maksimum"
                        config={config}
                        errors={errors}
                        inputMode="decimal"
                    />
                    {defaultInput}
                </>
            );
        case 'money':
            return (
                <>
                    <ConfigInput
                        name="min"
                        label="Nilai minimum (Rp)"
                        hint="Default 0."
                        config={config}
                        errors={errors}
                        inputMode="decimal"
                    />
                    <ConfigInput
                        name="max"
                        label="Nilai maksimum (Rp)"
                        config={config}
                        errors={errors}
                        inputMode="decimal"
                    />
                    {defaultInput}
                </>
            );
        case 'percentage':
            return (
                <>
                    <ConfigInput
                        name="scale"
                        label="Jumlah angka desimal"
                        hint="0–4."
                        config={config}
                        errors={errors}
                        inputMode="numeric"
                    />
                    {defaultInput}
                </>
            );
        case 'boolean':
            return (
                <Field
                    id="config_default"
                    label="Nilai default"
                    error={errors['config.default']}
                >
                    {(aria) => (
                        <NativeSelect
                            {...aria}
                            name="config[default]"
                            defaultValue={
                                config.default === true
                                    ? '1'
                                    : config.default === false
                                      ? '0'
                                      : ''
                            }
                        >
                            <option value="">Tanpa default</option>
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </NativeSelect>
                    )}
                </Field>
            );
        case 'date':
            return (
                <>
                    <ConfigInput
                        name="min"
                        label="Tanggal paling awal"
                        hint="Format YYYY-MM-DD atau today."
                        config={config}
                        errors={errors}
                        mono
                    />
                    <ConfigInput
                        name="max"
                        label="Tanggal paling akhir"
                        hint="Format YYYY-MM-DD atau today."
                        config={config}
                        errors={errors}
                        mono
                    />
                    {defaultInput}
                </>
            );
        case 'enum':
            return (
                <>
                    <OptionsEditor config={config} errors={errors} />
                    {defaultInput}
                </>
            );
        case 'multi_enum':
            return (
                <>
                    <OptionsEditor config={config} errors={errors} />
                    <ConfigInput
                        name="max_selected"
                        label="Maksimum pilihan"
                        config={config}
                        errors={errors}
                        inputMode="numeric"
                    />
                </>
            );
        case 'relationship':
            return (
                <>
                    <Field
                        id="config_target_entity_id"
                        label="Entity target"
                        required
                        hint="Entity dalam aplikasi ini atau entity bersama yang sudah terbit."
                        error={errors['config.target_entity_id']}
                    >
                        {(aria) => (
                            <NativeSelect
                                {...aria}
                                name="config[target_entity_id]"
                                defaultValue={str(config.target_entity_id)}
                            >
                                <option value="" disabled>
                                    Pilih entity target
                                </option>
                                {targets.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </Field>
                    <Field
                        id="config_cardinality"
                        label="Jumlah yang boleh dipilih"
                        required
                        error={errors['config.cardinality']}
                    >
                        {(aria) => (
                            <NativeSelect
                                {...aria}
                                name="config[cardinality]"
                                defaultValue={
                                    str(config.cardinality) || 'many_to_one'
                                }
                            >
                                <option value="many_to_one">
                                    Satu (banyak-ke-satu)
                                </option>
                                <option value="many_to_many">
                                    Banyak (banyak-ke-banyak)
                                </option>
                            </NativeSelect>
                        )}
                    </Field>
                    <Field
                        id="config_on_target_delete"
                        label="Bila data target dihapus"
                        error={errors['config.on_target_delete']}
                    >
                        {(aria) => (
                            <NativeSelect
                                {...aria}
                                name="config[on_target_delete]"
                                defaultValue={
                                    str(config.on_target_delete) || 'restrict'
                                }
                            >
                                <option value="restrict">
                                    Tolak penghapusan target
                                </option>
                                <option value="nullify">
                                    Kosongkan relasi
                                </option>
                            </NativeSelect>
                        )}
                    </Field>
                    <ConfigInput
                        name="inverse_code"
                        label="Kode relasi balik"
                        hint="Opsional. Nama navigasi dari target, mis. realisasi."
                        config={config}
                        errors={errors}
                        mono
                    />
                </>
            );
        case 'file': {
            const selected = Array.isArray(config.mimes)
                ? (config.mimes as string[])
                : ['pdf'];
            return (
                <>
                    <fieldset className="space-y-2 rounded-md border p-4">
                        <legend className="px-1 font-medium">
                            Jenis berkas yang diizinkan (wajib)
                        </legend>
                        {errors['config.mimes'] && (
                            <p
                                id="config_mimes"
                                className="text-sm font-medium text-red-700 dark:text-red-300"
                            >
                                {errors['config.mimes']}
                            </p>
                        )}
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {fileExtensions.map((ext) => (
                                <label
                                    key={ext}
                                    className="flex min-h-11 items-center gap-2 md:min-h-9"
                                >
                                    <input
                                        type="checkbox"
                                        name="config[mimes][]"
                                        value={ext}
                                        defaultChecked={selected.includes(ext)}
                                        className="size-6 accent-primary"
                                    />
                                    <span className="font-mono">{ext}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <ConfigInput
                        name="max_kb"
                        label="Ukuran maksimum per berkas (KB)"
                        hint="Maks 20480 (20 MB). Default 5120."
                        config={config}
                        errors={errors}
                        inputMode="numeric"
                    />
                    <ConfigInput
                        name="max_files"
                        label="Jumlah berkas maksimum"
                        hint="1–10."
                        config={config}
                        errors={errors}
                        inputMode="numeric"
                    />
                </>
            );
        }
        case 'region':
            return (
                <>
                    <ConfigInput
                        name="level_min"
                        label="Tingkat wilayah paling atas"
                        hint="1 provinsi, 2 kabupaten/kota, 3 kecamatan, 4 desa/kelurahan."
                        config={config}
                        errors={errors}
                        inputMode="numeric"
                    />
                    <ConfigInput
                        name="level_max"
                        label="Tingkat wilayah paling rinci"
                        config={config}
                        errors={errors}
                        inputMode="numeric"
                    />
                </>
            );
        default:
            return null;
    }
}
