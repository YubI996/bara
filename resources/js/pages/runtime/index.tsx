import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import RecordController from '@/actions/App/Modules/Data/Http/Controllers/RecordController';
import { NativeSelect } from '@/components/form/native-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatValue } from '@/runtime/format';
import type { RuntimeEntity, RuntimeField, RuntimeValues } from '@/types';

type Props = {
    entity: RuntimeEntity;
    fields: RuntimeField[];
    rows: { id: string; title: string; values: RuntimeValues }[];
    filters: { q: string; f: Record<string, string> };
    next_cursor: string | null;
    prev_cursor: string | null;
    can: { create: boolean };
};

export default function RecordIndex({
    entity,
    fields,
    rows,
    filters,
    next_cursor,
    prev_cursor,
    can,
}: Props) {
    const route = { app: entity.application_code, entity: entity.code };
    const columns = fields
        .filter((f) => f.in_list && f.access !== 'hidden')
        .slice(0, 4);
    const filterable = fields.filter((f) => f.filterable);
    const pageLink = (cursor: string) =>
        RecordController.index(route, {
            query: { q: filters.q || undefined, f: filters.f, cursor },
        });

    return (
        <>
            <Head title={entity.name_plural} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={entity.name_plural}
                    description={entity.application_name}
                    actions={
                        can.create && (
                            <Button asChild className="min-h-11 md:min-h-9">
                                <Link href={RecordController.create(route)}>
                                    <Plus aria-hidden="true" />
                                    Tambah {entity.name}
                                </Link>
                            </Button>
                        )
                    }
                />

                <Form
                    {...RecordController.index.form(route)}
                    role="search"
                    aria-label={`Cari ${entity.name_plural}`}
                    className="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end"
                    options={{ preserveState: true, replace: true }}
                >
                    <div className="grid gap-2 md:w-72">
                        <Label htmlFor="q">Cari judul</Label>
                        <Input
                            id="q"
                            name="q"
                            type="search"
                            defaultValue={filters.q}
                            maxLength={100}
                            className="h-11 md:h-9"
                        />
                    </div>
                    {filterable.map((field) => (
                        <div key={field.code} className="grid gap-2 md:w-56">
                            <Label htmlFor={`f_${field.code}`}>
                                {field.label}
                            </Label>
                            <NativeSelect
                                id={`f_${field.code}`}
                                name={`f[${field.code}]`}
                                defaultValue={filters.f[field.code] ?? ''}
                            >
                                <option value="">Semua</option>
                                {field.type === 'boolean' ? (
                                    <>
                                        <option value="1">Ya</option>
                                        <option value="0">Tidak</option>
                                    </>
                                ) : (
                                    field.options.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))
                                )}
                            </NativeSelect>
                        </div>
                    ))}
                    <Button
                        type="submit"
                        variant="secondary"
                        className="min-h-11 md:min-h-9"
                    >
                        <Search aria-hidden="true" />
                        Terapkan
                    </Button>
                </Form>

                {rows.length === 0 ? (
                    <p
                        role="status"
                        className="rounded-lg border border-dashed p-6 text-center"
                    >
                        {filters.q || Object.keys(filters.f).length > 0
                            ? 'Tidak ada data yang cocok dengan pencarian.'
                            : 'Belum ada data.'}
                    </p>
                ) : (
                    <div
                        className="overflow-x-auto rounded-lg border"
                        role="region"
                        aria-labelledby="records-caption"
                        tabIndex={0}
                    >
                        <table className="w-full text-left text-sm">
                            <caption id="records-caption" className="sr-only">
                                Daftar {entity.name_plural}, terbaru di atas
                            </caption>
                            <thead className="bg-muted/60">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Judul
                                    </th>
                                    {columns.map((c) => (
                                        <th
                                            key={c.code}
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            {c.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-normal"
                                        >
                                            <Link
                                                href={RecordController.show({
                                                    ...route,
                                                    record: row.id,
                                                })}
                                                className="inline-flex min-h-11 items-center font-medium underline underline-offset-4 md:min-h-0"
                                            >
                                                {row.title}
                                            </Link>
                                        </th>
                                        {columns.map((c) => (
                                            <td
                                                key={c.code}
                                                className="px-4 py-3"
                                            >
                                                {formatValue(
                                                    c,
                                                    row.values[c.code],
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {(prev_cursor || next_cursor) && (
                    <nav aria-label="Halaman" className="flex gap-3">
                        {prev_cursor && (
                            <Link
                                href={pageLink(prev_cursor)}
                                className="inline-flex min-h-11 items-center underline underline-offset-4 md:min-h-9"
                                preserveScroll
                            >
                                ← Sebelumnya
                            </Link>
                        )}
                        {next_cursor && (
                            <Link
                                href={pageLink(next_cursor)}
                                className="inline-flex min-h-11 items-center underline underline-offset-4 md:min-h-9"
                                preserveScroll
                            >
                                Berikutnya →
                            </Link>
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

RecordIndex.layout = {
    breadcrumbs: [{ title: 'Data', href: RecordController.home() }],
};
