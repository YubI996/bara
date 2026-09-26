import { Form, Head, Link } from '@inertiajs/react';
import RecordController from '@/actions/App/Modules/Data/Http/Controllers/RecordController';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { FieldInput } from '@/runtime/field-input';
import type {
    ParentOption,
    RuntimeEntity,
    RuntimeField,
    RuntimeValues,
} from '@/types';

type Props = {
    entity: RuntimeEntity;
    fields: RuntimeField[];
    record: {
        id: string;
        title: string;
        lock_version: number;
        values: RuntimeValues;
    } | null;
    owners: ParentOption[];
    default_owner_id: string | null;
    timezone: string;
};

export default function RecordForm({
    entity,
    fields,
    record,
    owners,
    default_owner_id,
    timezone,
}: Props) {
    const route = { app: entity.application_code, entity: entity.code };
    const action = record
        ? RecordController.update.form({ ...route, record: record.id })
        : RecordController.store.form(route);
    const title = record ? `Ubah ${record.title}` : `Tambah ${entity.name}`;

    const labels: Record<string, string> = {
        owner_org_id: 'Unit pemilik',
        data: 'Data',
        lock_version: 'Konflik perubahan',
    };
    for (const f of fields) {
        labels[`data.${f.code}`] = f.label;
        labels[`files.${f.code}`] = f.label;
    }

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={entity.application_name}
                />

                <Form {...action} className="max-w-2xl space-y-6" noValidate>
                    {({ processing, errors }) => (
                        <>
                            <ErrorSummary errors={errors} labels={labels} />

                            {errors.lock_version && record && (
                                <div
                                    role="alert"
                                    className="rounded-md border-2 border-amber-700 p-4 dark:border-amber-400"
                                >
                                    <p className="font-medium">
                                        {errors.lock_version}
                                    </p>
                                    <Link
                                        href={RecordController.edit({
                                            ...route,
                                            record: record.id,
                                        })}
                                        className="mt-2 inline-flex min-h-11 items-center underline underline-offset-4 md:min-h-9"
                                    >
                                        Muat ulang data terbaru
                                    </Link>
                                </div>
                            )}

                            {record ? (
                                <input
                                    type="hidden"
                                    name="lock_version"
                                    value={record.lock_version}
                                />
                            ) : (
                                <Field
                                    id="owner_org_id"
                                    label="Unit pemilik"
                                    required
                                    hint="Unit yang bertanggung jawab atas data ini. Menentukan siapa yang boleh melihat dan mengubahnya."
                                    error={errors.owner_org_id}
                                >
                                    {(aria) => (
                                        <NativeSelect
                                            {...aria}
                                            name="owner_org_id"
                                            defaultValue={
                                                default_owner_id ?? ''
                                            }
                                        >
                                            <option value="" disabled>
                                                Pilih unit
                                            </option>
                                            {owners.map((o) => (
                                                <option
                                                    key={o.value}
                                                    value={o.value}
                                                >
                                                    {`${'— '.repeat(o.depth)}${o.label}`}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </Field>
                            )}

                            {fields.map((field) => (
                                <FieldInput
                                    key={field.code}
                                    field={field}
                                    value={record?.values[field.code]}
                                    errors={errors}
                                    timezone={timezone}
                                />
                            ))}

                            <div className="flex flex-wrap gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-h-11 md:min-h-9"
                                >
                                    {processing ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                                <Button
                                    asChild
                                    variant="secondary"
                                    className="min-h-11 md:min-h-9"
                                >
                                    <Link
                                        href={
                                            record
                                                ? RecordController.show({
                                                      ...route,
                                                      record: record.id,
                                                  })
                                                : RecordController.index(route)
                                        }
                                    >
                                        Batal
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

RecordForm.layout = {
    breadcrumbs: [{ title: 'Data', href: RecordController.home() }],
};
