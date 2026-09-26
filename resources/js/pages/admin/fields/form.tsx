import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ApplicationController from '@/actions/App/Modules/Metadata/Http/Controllers/ApplicationController';
import FieldController from '@/actions/App/Modules/Metadata/Http/Controllers/FieldController';
import { CheckboxField } from '@/components/form/checkbox-field';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { Textarea } from '@/components/form/textarea';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type {
    ApplicationSummary,
    EntitySummary,
    FieldSummary,
    FieldTypeOption,
    Option,
} from '@/types';
import { ConfigInputs } from './config-inputs';

type Props = {
    application: ApplicationSummary;
    entity: EntitySummary;
    field: FieldSummary | null;
    types: FieldTypeOption[];
    classifications: Option[];
    targets: Option[];
    file_extensions: string[];
};

const labels: Record<string, string> = {
    label: 'Label',
    code: 'Kode',
    help_text: 'Teks bantuan',
    type: 'Tipe',
    classification: 'Klasifikasi data',
    is_required: 'Wajib diisi',
    is_unique: 'Nilai unik',
    is_indexed: 'Diindeks',
    is_searchable: 'Dapat dicari',
    'config.max_length': 'Panjang maksimum',
    'config.pattern': 'Pola',
    'config.default': 'Nilai default',
    'config.min': 'Nilai minimum',
    'config.max': 'Nilai maksimum',
    'config.scale': 'Angka desimal',
    'config.options': 'Opsi pilihan',
    'config.target_entity_id': 'Entity target',
    'config.mimes': 'Jenis berkas',
    draft: 'Draft',
};

export default function FieldFormPage({
    application,
    entity,
    field,
    types,
    classifications,
    targets,
    file_extensions,
}: Props) {
    const [type, setType] = useState(field?.type ?? 'string');
    const [indexed, setIndexed] = useState(field?.is_indexed ?? false);
    const selected = types.find((t) => t.value === type);
    const action = field?.id
        ? FieldController.update.form({ entity: entity.id, field: field.id })
        : FieldController.store.form(entity);
    const title = field ? `Ubah field: ${field.label}` : 'Tambah field';

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={`Draft entity ${entity.name} (${application.name}). Perubahan berlaku setelah versi dipublikasikan.`}
                />

                <Form {...action} className="max-w-2xl space-y-6" noValidate>
                    {({ processing, errors }) => (
                        <>
                            <ErrorSummary errors={errors} labels={labels} />

                            <Field
                                id="label"
                                label={labels.label}
                                required
                                hint="Teks yang dilihat pengguna di form. Harus unik dalam entity."
                                error={errors.label}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        name="label"
                                        defaultValue={field?.label}
                                        maxLength={120}
                                        className="h-11 md:h-9"
                                    />
                                )}
                            </Field>

                            <Field
                                id="code"
                                label={labels.code}
                                required
                                hint="Nama teknis di data dan API. Huruf kecil, angka, garis bawah. Aman diganti karena data dipetakan lewat kunci internal."
                                error={errors.code}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        name="code"
                                        defaultValue={field?.code}
                                        maxLength={63}
                                        spellCheck={false}
                                        autoComplete="off"
                                        className="h-11 font-mono md:h-9"
                                    />
                                )}
                            </Field>

                            <Field
                                id="help_text"
                                label={labels.help_text}
                                hint="Petunjuk pengisian yang tampil di bawah label."
                                error={errors.help_text}
                            >
                                {(aria) => (
                                    <Textarea
                                        {...aria}
                                        name="help_text"
                                        defaultValue={field?.help_text ?? ''}
                                        maxLength={500}
                                    />
                                )}
                            </Field>

                            <Field
                                id="type"
                                label={labels.type}
                                required
                                hint={
                                    field
                                        ? 'Mengubah tipe field yang sudah terbit hanya bisa untuk pasangan yang kompatibel (mis. bilangan bulat → desimal).'
                                        : undefined
                                }
                                error={errors.type}
                            >
                                {(aria) => (
                                    <NativeSelect
                                        {...aria}
                                        name="type"
                                        value={type}
                                        onChange={(e) =>
                                            setType(e.target.value)
                                        }
                                    >
                                        {types.map((t) => (
                                            <option
                                                key={t.value}
                                                value={t.value}
                                            >
                                                {t.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </Field>

                            <fieldset className="space-y-4 rounded-md border p-4">
                                <legend className="px-1 font-medium">
                                    Pengaturan {selected?.label ?? 'tipe'}
                                </legend>
                                <ConfigInputs
                                    key={type}
                                    type={type}
                                    config={
                                        field?.type === type ? field.config : {}
                                    }
                                    errors={errors}
                                    targets={targets}
                                    fileExtensions={file_extensions}
                                    supportsDefault={
                                        selected?.supports_default ?? false
                                    }
                                />
                            </fieldset>

                            <Field
                                id="classification"
                                label={labels.classification}
                                required
                                hint="Data pribadi (UU 27/2022) butuh persetujuan Pejabat PDP sebelum terbit dan disembunyikan dari role tanpa izin."
                                error={errors.classification}
                            >
                                {(aria) => (
                                    <NativeSelect
                                        {...aria}
                                        name="classification"
                                        defaultValue={
                                            field?.classification ?? 'internal'
                                        }
                                    >
                                        {classifications.map((c) => (
                                            <option
                                                key={c.value}
                                                value={c.value}
                                            >
                                                {c.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </Field>

                            <fieldset className="space-y-4">
                                <legend className="font-medium">Aturan</legend>
                                <CheckboxField
                                    id="is_required"
                                    name="is_required"
                                    label={labels.is_required}
                                    defaultChecked={field?.is_required}
                                    error={errors.is_required}
                                />
                                {selected?.supports_index && (
                                    <CheckboxField
                                        id="is_indexed"
                                        name="is_indexed"
                                        label={labels.is_indexed}
                                        hint="Mempercepat filter dan urutan. Maksimal 8 field per entity."
                                        checked={indexed}
                                        onChange={setIndexed}
                                        error={errors.is_indexed}
                                    />
                                )}
                                {selected?.supports_index && (
                                    <CheckboxField
                                        id="is_unique"
                                        name="is_unique"
                                        label={labels.is_unique}
                                        hint="Tidak boleh ada dua data dengan nilai sama. Butuh index."
                                        defaultChecked={field?.is_unique}
                                        disabled={!indexed}
                                        error={errors.is_unique}
                                    />
                                )}
                                {selected?.supports_search && (
                                    <CheckboxField
                                        id="is_searchable"
                                        name="is_searchable"
                                        label={labels.is_searchable}
                                        hint="Ikut dalam pencarian teks."
                                        defaultChecked={field?.is_searchable}
                                        error={errors.is_searchable}
                                    />
                                )}
                            </fieldset>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="min-h-11 md:min-h-9"
                            >
                                {processing
                                    ? 'Menyimpan…'
                                    : field
                                      ? 'Simpan field'
                                      : 'Tambah ke draft'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

FieldFormPage.layout = {
    breadcrumbs: [
        { title: 'Aplikasi', href: ApplicationController.index() },
        { title: 'Field', href: '#' },
    ],
};
