import { Form } from '@inertiajs/react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { Textarea } from '@/components/form/textarea';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { RouteFormDefinition } from '@/wayfinder';
import type { EntitySummary, Option } from '@/types';

const labels: Record<string, string> = {
    code: 'Kode entity',
    name: 'Nama',
    name_plural: 'Nama jamak',
    description: 'Deskripsi',
    default_visibility: 'Visibilitas bawaan',
    is_shared: 'Dibagikan ke aplikasi lain',
    title_template: 'Template judul',
};

type Props = {
    action: RouteFormDefinition<'post' | 'put'>;
    visibilities: Option[];
    entity?: EntitySummary;
    submitLabel: string;
};

export function EntityForm({
    action,
    visibilities,
    entity,
    submitLabel,
}: Props) {
    return (
        <Form
            {...action}
            className="max-w-2xl space-y-6"
            noValidate
            options={{ preserveScroll: true }}
        >
            {({ processing, errors }) => (
                <>
                    <ErrorSummary errors={errors} labels={labels} />

                    {!entity && (
                        <Field
                            id="code"
                            label={labels.code}
                            required
                            hint="Huruf kecil, angka, garis bawah. Contoh: realisasi. Tidak dapat diubah."
                            error={errors.code}
                        >
                            {(aria) => (
                                <Input
                                    {...aria}
                                    name="code"
                                    autoComplete="off"
                                    spellCheck={false}
                                    maxLength={63}
                                    className="h-11 font-mono md:h-9"
                                />
                            )}
                        </Field>
                    )}

                    <div className="grid gap-6 md:grid-cols-2">
                        <Field
                            id="name"
                            label={labels.name}
                            required
                            hint="Contoh: Realisasi Kegiatan."
                            error={errors.name}
                        >
                            {(aria) => (
                                <Input
                                    {...aria}
                                    name="name"
                                    defaultValue={entity?.name}
                                    maxLength={120}
                                    className="h-11 md:h-9"
                                />
                            )}
                        </Field>
                        <Field
                            id="name_plural"
                            label={labels.name_plural}
                            required
                            hint="Dipakai sebagai judul daftar."
                            error={errors.name_plural}
                        >
                            {(aria) => (
                                <Input
                                    {...aria}
                                    name="name_plural"
                                    defaultValue={entity?.name_plural}
                                    maxLength={120}
                                    className="h-11 md:h-9"
                                />
                            )}
                        </Field>
                    </div>

                    <Field
                        id="description"
                        label={labels.description}
                        error={errors.description}
                    >
                        {(aria) => (
                            <Textarea
                                {...aria}
                                name="description"
                                defaultValue={entity?.description ?? ''}
                                maxLength={1000}
                            />
                        )}
                    </Field>

                    <Field
                        id="title_template"
                        label={labels.title_template}
                        hint="Cara menyusun judul tiap data, memakai kode field dalam kurung kurawal. Contoh: {nama} ({tahun}). Boleh dikosongkan dulu."
                        error={errors.title_template}
                    >
                        {(aria) => (
                            <Input
                                {...aria}
                                name="title_template"
                                defaultValue={entity?.title_template ?? ''}
                                maxLength={200}
                                spellCheck={false}
                                className="h-11 font-mono md:h-9"
                            />
                        )}
                    </Field>

                    <Field
                        id="default_visibility"
                        label={labels.default_visibility}
                        required
                        hint="Siapa yang boleh membaca data baru di luar unit pemiliknya."
                        error={errors.default_visibility}
                    >
                        {(aria) => (
                            <NativeSelect
                                {...aria}
                                name="default_visibility"
                                defaultValue={
                                    entity?.default_visibility ?? 'private'
                                }
                            >
                                {visibilities.map((v) => (
                                    <option key={v.value} value={v.value}>
                                        {v.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </Field>

                    <CheckboxField
                        id="is_shared"
                        name="is_shared"
                        label={labels.is_shared}
                        hint="Centang bila entity ini boleh direferensikan aplikasi lain (misalnya Program dipakai Monitoring dan Kolaborasi)."
                        defaultChecked={entity?.is_shared ?? false}
                        error={errors.is_shared}
                    />

                    <Button
                        type="submit"
                        disabled={processing}
                        className="min-h-11 md:min-h-9"
                    >
                        {processing ? 'Menyimpan…' : submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
