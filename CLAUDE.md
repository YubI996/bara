# CLAUDE.md — Panduan untuk Agen AI & Kontributor

## Proyek
BARA: platform sistem informasi metadata-driven untuk satu Pemda (lihat `README.md`).
Status: **perencanaan selesai, implementasi belum dimulai.** Mulai dari M0 di `docs/12-roadmap-dan-milestone.md`.

## Wajib dibaca sebelum menulis kode
1. `docs/02-glosarium.md`: pakai istilah persis seperti di sini.
2. `docs/04-model-data.md`: skema adalah kontrak. Perubahan skema wajib memperbarui dokumen ini.
3. ADR di `docs/adr/`. Jangan melanggar keputusan yang berstatus "Diterima" tanpa membuat ADR baru.

## Stack
Laravel 13 · PHP 8.5 · PostgreSQL 18 · Redis · Inertia v3 · React 19 · TypeScript strict · Tailwind v4 · shadcn/ui · Pest 4 · Vitest · Playwright + axe.

## Aturan keras
- `declare(strict_types=1);` di setiap file PHP. PHPStan level max. TS `strict`, tanpa `any`.
- Modul di `app/Modules/{Nama}`. Akses antarmodul hanya lewat `Contracts/`.
- Controller tipis: validasi (FormRequest) → Action → respons.
- Semua query data bisnis lewat repository **ber-scope** (`ScopedRecordQuery`). Tidak ada query `records`/`objects` tanpa scope kecuali di `SystemContext`.
- Tidak ada SQL dari input pengguna. Identifier hanya dari metadata yang sudah divalidasi. Nilai selalu lewat binding.
- Tidak memakai `eval`, `symfony/expression-language`, atau `DB::raw` dengan interpolasi input.
- Setiap Action yang mengubah data: satu transaksi berisi perubahan + `AuditLogger` + `EventRecorder` (outbox).
- Field dengan classification `personal`/`personal_specific` tidak boleh muncul di log, payload event, atau props Inertia tanpa lewat `FieldGate`.
- Hindari N+1: relasi di list di-eager load per batch. Tes jumlah query untuk endpoint list.
- UI wajib WCAG 2.2 AA (`docs/11-frontend-dan-aksesibilitas.md`). Axe test untuk setiap halaman baru.
- Metadata published bersifat immutable. Perubahan dilakukan lewat versi baru.

## Perintah (akan tersedia setelah M0)
```bash
composer test        # Pest
composer analyse     # PHPStan
composer lint        # Pint --test
npm run typecheck && npm run lint && npm run test
npx playwright test  # e2e + axe
```

## Bahasa
Dokumen & UI: Bahasa Indonesia. Kode, nama tabel/kolom, commit message: Bahasa Inggris.
