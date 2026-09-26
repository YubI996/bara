import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import RecordController from '@/actions/App/Modules/Data/Http/Controllers/RecordController';
import { ConfirmAction } from '@/components/confirm-action';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { usePemdaTimezone } from '@/hooks/use-pemda';
import {
    formatBytes,
    formatDateTime,
    formatValue,
    isFileList,
} from '@/runtime/format';
import type { RuntimeEntity, RuntimeField, RuntimeValues } from '@/types';

type Props = {
    entity: RuntimeEntity;
    fields: RuntimeField[];
    record: {
        id: string;
        title: string;
        values: RuntimeValues;
        owner_name: string | null;
        created_at: string | null;
        updated_at: string | null;
    };
    can: { update: boolean; delete: boolean };
};

export default function RecordShow({ entity, fields, record, can }: Props) {
    const route = {
        app: entity.application_code,
        entity: entity.code,
        record: record.id,
    };

    const timezone = usePemdaTimezone();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Data', href: RecordController.home() },
            {
                title: entity.name_plural,
                href: RecordController.index({
                    app: entity.application_code,
                    entity: entity.code,
                }),
            },
            { title: record.title },
        ],
    });

    return (
        <>
            <Head title={record.title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={record.title}
                    description={
                        <>
                            <Link
                                href={RecordController.index({
                                    app: entity.application_code,
                                    entity: entity.code,
                                })}
                                className="underline underline-offset-4"
                            >
                                {entity.name_plural}
                            </Link>{' '}
                            · Pemilik: {record.owner_name ?? '—'}
                            <span className="block">
                                Dibuat{' '}
                                {formatDateTime(record.created_at, timezone)} ·
                                Terakhir diubah{' '}
                                {formatDateTime(record.updated_at, timezone)}
                            </span>
                        </>
                    }
                    actions={
                        <>
                            {can.update && (
                                <Button
                                    asChild
                                    variant="secondary"
                                    className="min-h-11 md:min-h-9"
                                >
                                    <Link href={RecordController.edit(route)}>
                                        <Pencil aria-hidden="true" />
                                        Ubah
                                    </Link>
                                </Button>
                            )}
                            {can.delete && (
                                <ConfirmAction
                                    trigger="Hapus…"
                                    title={`Hapus “${record.title}”?`}
                                    description="Data akan dihapus dari daftar dan tidak bisa dipulihkan sendiri. Hubungi admin bila perlu dipulihkan. Penghapusan ditolak bila data ini masih dirujuk data lain."
                                    confirmLabel="Ya, hapus"
                                    processingLabel="Menghapus…"
                                    method="delete"
                                    url={RecordController.destroy.url(route)}
                                />
                            )}
                        </>
                    }
                />

                <dl className="grid max-w-3xl gap-4 sm:grid-cols-[minmax(0,14rem)_1fr]">
                    {fields.map((field) => {
                        const value = record.values[field.code];
                        return (
                            <div key={field.code} className="contents">
                                <dt className="font-medium text-muted-foreground">
                                    {field.label}
                                </dt>
                                <dd className="min-w-0 break-words">
                                    {field.type === 'file' &&
                                    isFileList(value) ? (
                                        value.length === 0 ? (
                                            '—'
                                        ) : (
                                            <ul className="grid gap-1">
                                                {value.map((file) => (
                                                    <li key={file.id}>
                                                        {file.available ? (
                                                            <a
                                                                href={file.url}
                                                                className="underline underline-offset-4"
                                                            >
                                                                Unduh{' '}
                                                                {file.name} (
                                                                {formatBytes(
                                                                    file.size,
                                                                )}
                                                                )
                                                            </a>
                                                        ) : (
                                                            <span>
                                                                {file.name}{' '}
                                                                (sedang
                                                                diperiksa
                                                                antivirus)
                                                            </span>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )
                                    ) : field.type === 'rich_text' &&
                                      typeof value === 'string' ? (
                                        // HTML sudah disanitasi allowlist di server (RichTextSanitizer).
                                        <div
                                            className="prose prose-sm dark:prose-invert max-w-none"
                                            dangerouslySetInnerHTML={{
                                                __html: value,
                                            }}
                                        />
                                    ) : field.type === 'text' ? (
                                        <p className="whitespace-pre-line">
                                            {formatValue(
                                                field,
                                                value,
                                                timezone,
                                            )}
                                        </p>
                                    ) : (
                                        <span
                                            className={
                                                field.access === 'masked'
                                                    ? 'font-mono'
                                                    : undefined
                                            }
                                        >
                                            {formatValue(
                                                field,
                                                value,
                                                timezone,
                                            )}
                                        </span>
                                    )}
                                    {field.access === 'masked' && (
                                        <span className="block text-sm text-muted-foreground">
                                            Disamarkan (
                                            {field.classification_label})
                                        </span>
                                    )}
                                </dd>
                            </div>
                        );
                    })}
                </dl>
            </div>
        </>
    );
}

RecordShow.layout = {
    breadcrumbs: [{ title: 'Data', href: RecordController.home() }],
};
