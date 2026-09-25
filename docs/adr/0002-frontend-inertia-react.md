# ADR 0002 — Frontend: Inertia v3 + React 19 + TypeScript

- **Status:** Diterima (menggantikan pilihan "Vue" di rencana awal)
- **Tanggal:** 2026-09-25

## Konteks
Rencana awal menyebut Vue. Pengguna/tim terbiasa dengan React/Next.js dan TypeScript. UI runtime dihasilkan dari metadata (form & view generik), sehingga dibutuhkan ekosistem komponen aksesibel yang matang.

## Keputusan
- Inertia v3 (butuh React 19+, Vite 7+) di atas Laravel. Tidak ada SPA + API terpisah untuk UI internal.
- TypeScript `strict: true`. Tipe props di-generate dari DTO PHP (`spatie/laravel-typescript-transformer`).
- Tailwind CSS v4 + shadcn/ui (Radix primitives) untuk komponen yang sudah menangani ARIA dan keyboard.
- Chart: Recharts, selalu disertai alternatif tabel.

## Alternatif
| Opsi | Kelebihan | Kekurangan |
|---|---|---|
| Inertia + Vue | Sesuai rencana awal, ringan | Tim lebih fasih React; ekosistem primitive a11y React lebih luas |
| Next.js terpisah + Laravel API | Server Components | Dua deployment, auth lintas domain, duplikasi validasi/otorisasi |
| Livewire | Tanpa build JS | Form dinamis kompleks (combobox async, kondisi tampil) lebih sulit dibuat aksesibel dan responsif |

## Konsekuensi
- (+) Satu deployment, session auth biasa, dan validasi tetap di server.
- (+) Pengetahuan React/TS tim terpakai.
- (−) Tanpa React Server Components. Tidak masalah karena data diambil di controller.
- API publik tetap dibangun terpisah (`/api/v1`) untuk mesin dan SPLP.
