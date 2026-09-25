import { Form } from '@inertiajs/react';
import type { RouteFormDefinition } from '@/wayfinder';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Option, Organization, ParentOption } from '@/types';

type Props = {
    action: RouteFormDefinition<'post' | 'put'>;
    parents: ParentOption[];
    kinds: Option[];
    organization?: Organization;
    defaultParentId?: string | null;
    submitLabel: string;
};

const labels: Record<string, string> = {
    parent_id: 'Unit induk',
    code: 'Kode',
    name: 'Nama unit',
    short_name: 'Singkatan',
    kind: 'Jenis unit',
};

/** Indentasi opsi induk dengan em dash agar hierarki terbaca juga oleh pembaca layar. */
function indent(option: ParentOption): string {
    return `${'— '.repeat(option.depth)}${option.label}`;
}

export function OrganizationForm({
    action,
    parents,
    kinds,
    organization,
    defaultParentId,
    submitLabel,
}: Props) {
    const isEdit = organization !== undefined;
    const isRoot = organization?.is_root ?? false;

    return (
        <Form {...action} className="max-w-2xl space-y-6" noValidate>
            {({ processing, errors }) => (
                <>
                    <ErrorSummary errors={errors} labels={labels} />

                    {!isRoot && (
                        <Field
                            id="parent_id"
                            label={labels.parent_id}
                            required
                            hint="Unit tempat unit ini bernaung. Hanya unit aktif dalam kewenangan Anda yang ditampilkan."
                            error={errors.parent_id}
                        >
                            {(aria) => (
                                <NativeSelect
                                    {...aria}
                                    name="parent_id"
                                    defaultValue={
                                        organization?.parent_id ??
                                        defaultParentId ??
                                        ''
                                    }
                                >
                                    <option value="" disabled>
                                        Pilih unit induk
                                    </option>
                                    {parents.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {indent(option)}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </Field>
                    )}

                    {isEdit ? (
                        <div className="grid gap-1">
                            <p className="text-sm font-medium">Kode</p>
                            <p className="font-mono text-sm">
                                {organization.code}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Kode bersifat tetap karena dipakai sebagai
                                pengenal di seluruh platform.
                            </p>
                        </div>
                    ) : (
                        <Field
                            id="code"
                            label={labels.code}
                            required
                            hint="Huruf kecil, angka, dan garis bawah; diawali huruf; 2–63 karakter. Contoh: dinkes, bid_p2p. Tidak dapat diubah setelah disimpan."
                            error={errors.code}
                        >
                            {(aria) => (
                                <Input
                                    {...aria}
                                    name="code"
                                    autoComplete="off"
                                    spellCheck={false}
                                    className="h-11 font-mono md:h-9"
                                    maxLength={63}
                                />
                            )}
                        </Field>
                    )}

                    <Field
                        id="name"
                        label={labels.name}
                        required
                        hint="Nama resmi sesuai nomenklatur. Contoh: Dinas Kesehatan."
                        error={errors.name}
                    >
                        {(aria) => (
                            <Input
                                {...aria}
                                name="name"
                                defaultValue={organization?.name}
                                autoComplete="organization"
                                className="h-11 md:h-9"
                                maxLength={200}
                            />
                        )}
                    </Field>

                    <Field
                        id="short_name"
                        label={labels.short_name}
                        hint="Contoh: Dinkes."
                        error={errors.short_name}
                    >
                        {(aria) => (
                            <Input
                                {...aria}
                                name="short_name"
                                defaultValue={organization?.short_name ?? ''}
                                autoComplete="off"
                                className="h-11 md:h-9"
                                maxLength={50}
                            />
                        )}
                    </Field>

                    <Field
                        id="kind"
                        label={labels.kind}
                        required
                        error={errors.kind}
                    >
                        {(aria) => (
                            <NativeSelect
                                {...aria}
                                name="kind"
                                defaultValue={organization?.kind ?? ''}
                            >
                                <option value="" disabled>
                                    Pilih jenis unit
                                </option>
                                {kinds.map((kind) => (
                                    <option key={kind.value} value={kind.value}>
                                        {kind.label}
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
