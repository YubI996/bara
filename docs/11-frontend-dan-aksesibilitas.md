# 11 — Frontend & Aksesibilitas

## 1. Stack

Inertia v3 + React 19 + TypeScript strict + Tailwind CSS v4 + shadcn/ui (Radix primitives). Alasan pemilihan ada di [ADR 0002](adr/0002-frontend-inertia-react.md).

```text
resources/js/
├── app.tsx                    # createInertiaApp (Vite plugin Inertia v3)
├── pages/
│   ├── runtime/Index.tsx      # daftar record generik
│   ├── runtime/Form.tsx       # create/edit generik
│   ├── runtime/Show.tsx
│   ├── dashboards/Show.tsx
│   └── admin/metadata/...     # builder berbasis form (bukan drag-drop)
├── runtime/
│   ├── FormRenderer.tsx       # compiled_schema.ui → komponen field
│   ├── fields/                # 1 komponen per FieldType.uiComponent
│   ├── TableRenderer.tsx
│   ├── ViewRenderer.tsx       # kpi, bar, line, pie, stacked
│   └── expr/                  # TsEvaluator untuk visible_when (AST sama dengan PHP)
├── components/ui/             # shadcn/ui
└── types/generated.ts         # dari DTO PHP (spatie/laravel-typescript-transformer)
```

## 2. Kontrak server → klien

- Props Inertia adalah DTO bertipe (`EntityPageProps`, `RecordDTO`). Field sudah difilter `FieldGate` di server.
- Klien **tidak pernah** menerima field di luar clearance user, termasuk sebagai `hidden`.
- Validasi klien (required, min/max, pattern) diturunkan dari `compiled_schema.ui` hanya untuk UX. Error resmi berasal dari server (Inertia `errors`).

## 3. Target: WCAG 2.2 level AA

WCAG 2.2 adalah versi W3C Recommendation terbaru. Semua halaman runtime, admin, dan dashboard wajib memenuhi level A dan AA.

### 3.1 Aturan wajib per komponen

| Area | Aturan | SC WCAG |
|---|---|---|
| Kontras | Teks ≥ 4.5:1, teks besar ≥ 3:1, komponen UI & fokus ≥ 3:1. Token warna diuji otomatis di CI. | 1.4.3, 1.4.11 |
| Fokus | Outline fokus terlihat (≥ 2px, kontras 3:1), tidak tertutup header sticky | 2.4.7, 2.4.11 |
| Target sentuh | ≥ 24×24 CSS px (desain memakai 44×44 untuk tombol utama) | 2.5.8 |
| Label | Setiap input punya `<label>` terhubung. Placeholder bukan label. Field wajib ditandai teks "(wajib)", bukan hanya `*` merah. | 1.3.1, 3.3.2 |
| Error | Ringkasan error di atas form (fokus dipindah ke sana, `role="alert"`), setiap error terhubung via `aria-describedby`, dengan saran perbaikan | 3.3.1, 3.3.3 |
| Keyboard | Semua fungsi bisa dijalankan dengan keyboard, tanpa jebakan fokus. Dialog mengembalikan fokus saat ditutup. | 2.1.1, 2.1.2 |
| Tabel data | `<table>` asli dengan `<th scope>` dan `<caption>`. Sort memakai `aria-sort`. Scroll horizontal di mobile diberi label region. | 1.3.1 |
| Chart | Setiap chart punya judul, ringkasan teks (nilai tertinggi/terendah/tren), dan tombol "Lihat sebagai tabel". Warna bukan satu-satunya pembeda (pola/label langsung). | 1.1.1, 1.4.1 |
| Reflow | Tanpa scroll horizontal halaman pada lebar 320px (kecuali tabel data) | 1.4.10 |
| Bahasa | `<html lang="id">` | 3.1.1 |
| Status dinamis | Toast/flash memakai `aria-live="polite"`. Loading Inertia diumumkan. | 4.1.3 |
| Waktu | Session timeout memberi peringatan ≥ 2 menit sebelumnya dan bisa diperpanjang | 2.2.1 |
| Autentikasi | Tanpa tes kognitif. Password manager didukung (paste diizinkan). | 3.3.8 |
| Input berulang | Data yang sudah diisi di langkah sebelumnya tidak diminta ulang | 3.3.7 |
| Drag | Semua operasi drag (urutan field, layout dashboard) punya alternatif tombol naik/turun | 2.5.7 |
| Bantuan | Posisi tautan bantuan konsisten di setiap halaman | 3.2.6 |

### 3.2 Metadata sebagai sumber aksesibilitas

Karena UI dihasilkan dari metadata, aksesibilitas juga dijaga di level metadata:
- `label` wajib dan unik dalam form. `help_text` dirender sebagai deskripsi terhubung.
- Validator publish menolak: label kosong, dua field dengan label sama, enum tanpa label opsi, chart tanpa judul.
- `EntitySelector` memakai pola combobox ARIA 1.2 (Radix/cmdk) dengan pengumuman jumlah hasil.

### 3.3 Pengujian

| Lapis | Alat | Kapan |
|---|---|---|
| Unit komponen | Vitest + Testing Library (`getByRole`) | Setiap PR |
| Otomatis halaman | Playwright + `@axe-core/playwright` pada halaman runtime yang digenerate dari entity fixture berisi **semua** tipe field | Setiap PR (gagal = merge ditolak) |
| Kontras token | Skrip cek palet di light & dark mode | Setiap PR |
| Manual | Keyboard-only + NVDA (Windows) + TalkBack (Android) pada 5 alur kunci | Setiap milestone |
| Pengguna awam | Uji usability dengan operator OPD (≥ 5 orang) | Sebelum MVP1 & MVP2 |

## 4. Desain visual

- Mobile-first, dan operator OPD sering memakai laptop spesifikasi rendah. Bundle awal ≤ 200 KB gzip, dengan code splitting per halaman (bawaan Inertia).
- Light/dark mode memakai token CSS di `:root`, dan kedua mode diuji kontrasnya.
- Tipografi: font sistem atau satu font variabel self-hosted (tidak memakai CDN pihak ketiga, supaya data tidak bocor dan tetap berjalan di jaringan intranet).
- Chart: Recharts (React) dengan palet yang lolos kontras 3:1 terhadap latar.
- Format lokal: `Intl.NumberFormat('id-ID')`, Rupiah `Rp 1.250.000`, tanggal `25 Sep 2026`.
