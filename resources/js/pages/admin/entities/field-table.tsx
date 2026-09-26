import { Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import FieldController from '@/actions/App/Modules/Metadata/Http/Controllers/FieldController';
import { Button } from '@/components/ui/button';
import type { EntitySummary, FieldSummary } from '@/types';

type Props = {
    entity: EntitySummary;
    fields: FieldSummary[];
    caption: string;
    editable: boolean;
};

function flags(field: FieldSummary): string {
    const parts: string[] = [];
    if (field.is_required) parts.push('wajib');
    if (field.is_unique) parts.push('unik');
    if (field.is_indexed) parts.push('diindeks');
    if (field.is_searchable) parts.push('dapat dicari');
    return parts.join(', ') || '—';
}

export function FieldTable({ entity, fields, caption, editable }: Props) {
    const move = (field: FieldSummary, direction: 'up' | 'down') => {
        if (field.id === null) return;
        router.post(
            FieldController.move.url({ entity: entity.id, field: field.id }),
            { direction },
            { preserveScroll: true },
        );
    };

    const remove = (field: FieldSummary) => {
        if (field.id === null) return;
        router.delete(
            FieldController.destroy.url({ entity: entity.id, field: field.id }),
            {
                preserveScroll: true,
                onBefore: () =>
                    window.confirm(
                        `Hapus field “${field.label}” dari draft? Data lama tetap tersimpan setelah versi baru terbit.`,
                    ),
            },
        );
    };

    if (fields.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-6 text-center">
                Belum ada field.
            </p>
        );
    }

    return (
        <div
            className="overflow-x-auto rounded-lg border"
            role="region"
            aria-label={caption}
            tabIndex={0}
        >
            <table className="w-full text-left text-sm">
                <caption className="sr-only">{caption}</caption>
                <thead className="bg-muted/60">
                    <tr>
                        <th scope="col" className="px-3 py-3 font-medium">
                            No.
                        </th>
                        <th scope="col" className="px-3 py-3 font-medium">
                            Label
                        </th>
                        <th scope="col" className="px-3 py-3 font-medium">
                            Kode
                        </th>
                        <th scope="col" className="px-3 py-3 font-medium">
                            Tipe
                        </th>
                        <th scope="col" className="px-3 py-3 font-medium">
                            Aturan
                        </th>
                        <th scope="col" className="px-3 py-3 font-medium">
                            Klasifikasi
                        </th>
                        {editable && (
                            <th scope="col" className="px-3 py-3 font-medium">
                                <span className="sr-only">Aksi</span>
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {fields.map((field, index) => (
                        <tr
                            key={field.field_key}
                            className="border-t align-top"
                        >
                            <td className="px-3 py-3">{index + 1}</td>
                            <th scope="row" className="px-3 py-3 font-medium">
                                {field.label}
                            </th>
                            <td className="px-3 py-3 font-mono">
                                {field.code}
                            </td>
                            <td className="px-3 py-3">
                                {field.type_label}
                                {field.summary && (
                                    <span className="block text-muted-foreground">
                                        {field.summary}
                                    </span>
                                )}
                            </td>
                            <td className="px-3 py-3">{flags(field)}</td>
                            <td className="px-3 py-3">
                                {field.is_personal ? (
                                    <span className="inline-flex rounded-md border border-amber-700 px-2 py-0.5 text-xs font-medium text-amber-900 dark:border-amber-400 dark:text-amber-100">
                                        {field.classification_label}
                                    </span>
                                ) : (
                                    field.classification_label
                                )}
                            </td>
                            {editable && field.id !== null && (
                                <td className="px-3 py-2">
                                    <div className="flex flex-wrap items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-11 md:size-9"
                                            disabled={index === 0}
                                            onClick={() => move(field, 'up')}
                                            aria-label={`Naikkan ${field.label}`}
                                        >
                                            <ArrowUp aria-hidden="true" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-11 md:size-9"
                                            disabled={
                                                index === fields.length - 1
                                            }
                                            onClick={() => move(field, 'down')}
                                            aria-label={`Turunkan ${field.label}`}
                                        >
                                            <ArrowDown aria-hidden="true" />
                                        </Button>
                                        <Link
                                            href={FieldController.edit({
                                                entity: entity.id,
                                                field: field.id,
                                            })}
                                            className="inline-flex min-h-11 items-center px-2 font-medium underline underline-offset-4 md:min-h-9"
                                        >
                                            Ubah
                                            <span className="sr-only">
                                                {' '}
                                                {field.label}
                                            </span>
                                        </Link>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="min-h-11 text-red-700 hover:text-red-800 md:min-h-9 dark:text-red-300"
                                            onClick={() => remove(field)}
                                        >
                                            Hapus
                                            <span className="sr-only">
                                                {' '}
                                                {field.label}
                                            </span>
                                        </Button>
                                    </div>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
