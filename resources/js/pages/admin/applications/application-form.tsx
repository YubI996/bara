import { Form } from '@inertiajs/react';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { Textarea } from '@/components/form/textarea';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { RouteFormDefinition } from '@/wayfinder';
import type { ApplicationSummary, Option, ParentOption } from '@/types';

const labels: Record<string, string> = {
    code: 'Kode aplikasi',
    name: 'Nama aplikasi',
    description: 'Deskripsi',
    owner_org_id: 'Unit pemilik',
    status: 'Status',
};

type Props = {
    action: RouteFormDefinition<'post' | 'put'>;
    owners: ParentOption[];
    statuses: Option[];
    application?: ApplicationSummary;
    submitLabel: string;
};

export function ApplicationForm({
    action,
    owners,
    statuses,
    application,
    submitLabel,
}: Props) {
    return (
        <Form {...action} className="max-w-2xl space-y-6" noValidate>
            {({ processing, errors }) => (
                <>
                    <ErrorSummary errors={errors} labels={labels} />

                    {application ? (
                        <div className="grid gap-1">
                            <p className="text-sm font-medium">Kode aplikasi</p>
                            <p className="font-mono text-sm">
                                {application.code}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Kode tetap karena menjadi awalan permission dan
                                alamat aplikasi.
                            </p>
                        </div>
                    ) : (
                        <Field
                            id="code"
                            label={labels.code}
                            required
                            hint="Huruf kecil, angka, garis bawah; diawali huruf. Contoh: monev. Menjadi awalan permission (monev.kegiatan.view) dan tidak dapat diubah."
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

                    <Field
                        id="name"
                        label={labels.name}
                        required
                        hint="Contoh: Monitoring Program."
                        error={errors.name}
                    >
                        {(aria) => (
                            <Input
                                {...aria}
                                name="name"
                                defaultValue={application?.name}
                                maxLength={120}
                                className="h-11 md:h-9"
                            />
                        )}
                    </Field>

                    <Field
                        id="description"
                        label={labels.description}
                        error={errors.description}
                    >
                        {(aria) => (
                            <Textarea
                                {...aria}
                                name="description"
                                defaultValue={application?.description ?? ''}
                                maxLength={1000}
                            />
                        )}
                    </Field>

                    <Field
                        id="owner_org_id"
                        label={labels.owner_org_id}
                        required
                        hint="Unit yang bertanggung jawab atas aplikasi dan datanya (Produsen Data)."
                        error={errors.owner_org_id}
                    >
                        {(aria) => (
                            <NativeSelect
                                {...aria}
                                name="owner_org_id"
                                defaultValue={application?.owner_org_id ?? ''}
                            >
                                <option value="" disabled>
                                    Pilih unit pemilik
                                </option>
                                {owners.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {`${'— '.repeat(o.depth)}${o.label}`}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </Field>

                    <Field
                        id="status"
                        label={labels.status}
                        required
                        error={errors.status}
                    >
                        {(aria) => (
                            <NativeSelect
                                {...aria}
                                name="status"
                                defaultValue={application?.status ?? 'draft'}
                            >
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </Field>

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
