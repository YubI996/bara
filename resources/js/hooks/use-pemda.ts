import { usePage } from '@inertiajs/react';

/** Zona waktu tampilan Pemda (shared prop). Tanggal-waktu tidak boleh memakai zona browser. */
export function usePemdaTimezone(): string {
    return usePage().props.pemda.timezone;
}
