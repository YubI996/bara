import type { ReactNode } from 'react';
import { usePemdaTimezone } from '@/hooks/use-pemda';
import { cn } from '@/lib/utils';
import { formatDateTime } from '@/runtime/format';
import type { ConsumerRow } from '@/types';

const statusStyle: Record<ConsumerRow['status'], string> = {
    pending:
        'border-amber-700 text-amber-900 dark:border-amber-400 dark:text-amber-100',
    approved:
        'border-green-700 text-green-900 dark:border-green-400 dark:text-green-100',
    rejected:
        'border-red-700 text-red-900 dark:border-red-400 dark:text-red-100',
    revoked:
        'border-neutral-600 text-neutral-800 dark:border-neutral-300 dark:text-neutral-100',
};

type Props = {
    rows: ConsumerRow[];
    caption: string;
    /** Tampilkan kolom aplikasi pemakai (halaman Walidata). */
    showApplication?: boolean;
    actions?: (row: ConsumerRow) => ReactNode;
};

/** Tabel pengajuan pemakaian entity bersama, dipakai halaman aplikasi & halaman Walidata. */
export function ConsumerTable({
    rows,
    caption,
    showApplication,
    actions,
}: Props) {
    const timezone = usePemdaTimezone();

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
                        <th scope="col" className="px-4 py-3 font-medium">
                            Entity — pemilik
                        </th>
                        {showApplication && (
                            <th scope="col" className="px-4 py-3 font-medium">
                                Aplikasi pemakai
                            </th>
                        )}
                        <th scope="col" className="px-4 py-3 font-medium">
                            Status
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Alasan &amp; keputusan
                        </th>
                        {actions && (
                            <th scope="col" className="px-4 py-3 font-medium">
                                <span className="sr-only">Aksi</span>
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={`${row.entity_id}-${row.application_id}`}
                            className="border-t align-top"
                        >
                            <th scope="row" className="px-4 py-3 font-medium">
                                {row.entity}
                            </th>
                            {showApplication && (
                                <td className="px-4 py-3">{row.application}</td>
                            )}
                            <td className="px-4 py-3">
                                <span
                                    className={cn(
                                        'inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold',
                                        statusStyle[row.status],
                                    )}
                                >
                                    {row.status_label}
                                </span>
                            </td>
                            <td className="space-y-1 px-4 py-3">
                                <p>{row.reason}</p>
                                <p className="text-muted-foreground">
                                    Diajukan {row.requested_by},{' '}
                                    {formatDateTime(row.requested_at, timezone)}
                                </p>
                                {row.decided_at && (
                                    <p className="text-muted-foreground">
                                        Diputuskan {row.decided_by ?? 'sistem'},{' '}
                                        {formatDateTime(
                                            row.decided_at,
                                            timezone,
                                        )}
                                        {row.decision_note &&
                                            `: ${row.decision_note}`}
                                    </p>
                                )}
                            </td>
                            {actions && (
                                <td className="px-4 py-3">{actions(row)}</td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
