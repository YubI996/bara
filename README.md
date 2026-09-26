# BARA — Platform Sistem Informasi Terintegrasi & Pentahelix

Platform **metadata-driven** untuk satu Pemerintah Daerah: sistem informasi baru dibuat dari
definisi (entity, field, relasi, proses, indikator, view, workflow). Tidak lagi dari kode baru.
Semua aplikasi berdiri di atas identitas, master data, semantik, dan pengolahan yang sama.
Platform ini disiapkan untuk terhubung ke **SPLP** (Sistem Penghubung Layanan Pemerintah)
supaya bisa bertukar data antar-Pemda.

> Ukuran keberhasilan: **seberapa sedikit konsep, data, relasi, formula, dan proses yang harus
> didefinisikan ulang** saat membuat aplikasi baru.

## Status

**M0 (Fondasi) dan M1 (Metadata engine) selesai.** Tersedia: login + 2FA, struktur organisasi
berhierarki (ltree), otorisasi berbasis scope unit, audit log append-only, outbox event, serta
pembangun aplikasi → entity → field (15 tipe) dengan versi, diff perubahan, gate persetujuan
Pejabat PDP, publikasi skema, serta **halaman input data otomatis** untuk setiap entity terbit
(daftar, tambah, detail, ubah, hapus, lampiran) yang dibatasi scope unit dan clearance data.
Berikutnya M3 (Relationship), lihat [roadmap](docs/12-roadmap-dan-milestone.md).

## Menjalankan secara lokal (Windows + Laragon)

Kebutuhan: PHP 8.4+ (ekstensi `pdo_pgsql`, `pgsql`, `intl` aktif di `php.ini`), Composer 2,
Node 22, **PostgreSQL 18**. Laragon tidak menyertakan PostgreSQL 18, jadi pasang lewat installer
resmi (EDB) atau Docker Desktop (`docker run -d --name bara-pg -e POSTGRES_USER=bara -e POSTGRES_PASSWORD=secret -p 5432:5432 postgres:18`).

```bash
composer install
npm install
copy .env.example .env          # Linux/macOS: cp .env.example .env
php artisan key:generate
# isi DB_PASSWORD, BARA_PEMDA_CODE, BARA_PEMDA_NAME, BARA_ADMIN_EMAIL di .env
psql -U bara -c "CREATE DATABASE bara" -c "CREATE DATABASE bara_test"
php artisan migrate --seed      # password admin dicetak SEKALI bila BARA_ADMIN_PASSWORD kosong
php artisan bara:sync-access    # jalankan juga setiap selesai git pull / deploy
# beri diri Anda role operator aplikasi (setelah entity pertama terbit):
# php artisan bara:assign-role admin@example.test operator pemda --app=monev
npm run build
composer dev                    # server + queue + vite
```

**Tanda berhasil:** buka `http://localhost:8000`, login dengan admin, aktifkan 2FA di
_Pengaturan → Keamanan_, lalu menu **Organisasi** muncul dan bisa menambah unit.

**Kalau gagal, cek ini:**

- `could not find driver` → aktifkan `extension=pdo_pgsql` di `php.ini` Laragon.
- `type "ltree" does not exist` → user DB perlu hak `CREATE` extension (pakai superuser untuk migrate pertama).
- Menu Organisasi tidak muncul → 2FA belum aktif, atau user tidak punya role `platform_admin`.

Tes: `php artisan test` (butuh DB `bara_test`), `npm run test:e2e` (butuh DB `bara_e2e`).

## Keputusan utama (ringkas)

| Aspek              | Keputusan                                                             | ADR                                                     |
| ------------------ | --------------------------------------------------------------------- | ------------------------------------------------------- |
| Bentuk sistem      | Modular monolith, satu deployment                                     | [0001](docs/adr/0001-modular-monolith.md)               |
| Backend            | Laravel 13, PHP 8.5, strict types                                     | [0001](docs/adr/0001-modular-monolith.md)               |
| Frontend           | Inertia v3 + React 19 + TypeScript strict + Tailwind v4               | [0002](docs/adr/0002-frontend-inertia-react.md)         |
| Database           | PostgreSQL 18 (JSONB, ltree, PostGIS, RLS)                            | [0003](docs/adr/0003-postgresql.md)                     |
| Penyimpanan record | Hybrid: Core = tabel fisik, aplikasi = JSONB, relasi = `record_links` | [0004](docs/adr/0004-hybrid-storage.md)                 |
| Identitas objek    | UUIDv7 + registry `objects` (supertype)                               | [0005](docs/adr/0005-object-registry-uuidv7.md)         |
| Evolusi skema      | Metadata berversi (draft → published)                                 | [0006](docs/adr/0006-metadata-versioning.md)            |
| Otorisasi          | RBAC + scope organisasi (ltree) + field classification + RLS          | [0007](docs/adr/0007-authorization-model.md)            |
| Processing         | DSL JSON deklaratif → SQL (query builder), formula via parser sendiri | [0008](docs/adr/0008-processing-dsl.md)                 |
| Indikator          | Definisi berversi + snapshot nilai + lineage                          | [0009](docs/adr/0009-indicator-snapshot.md)             |
| Event              | Transactional outbox + listener idempotent                            | [0010](docs/adr/0010-transactional-outbox.md)           |
| Audit              | Append-only, dipartisi per bulan                                      | [0011](docs/adr/0011-audit-append-only.md)              |
| Multi-Pemda        | Single-tenant; federasi lewat SPLP, bukan database bersama            | [0012](docs/adr/0012-single-tenant-splp-federation.md)  |
| Auth               | Lokal + siap OIDC SSO; API mesin via OAuth2 client credentials        | [0013](docs/adr/0013-authentication.md)                 |
| Kunci data record  | JSONB berkunci `field_key` stabil, bukan kode field                   | [0014](docs/adr/0014-record-data-keyed-by-field-key.md) |

## Peta dokumen

| #   | Dokumen                                                           | Isi                                                       |
| --- | ----------------------------------------------------------------- | --------------------------------------------------------- |
| 00  | [Rencana awal](docs/00-rencana-awal.md)                           | Dokumen sumber (tidak diubah)                             |
| 01  | [Visi & ruang lingkup](docs/01-visi-dan-ruang-lingkup.md)         | Tujuan, batas, stakeholder, metrik sukses                 |
| 02  | [Glosarium](docs/02-glosarium.md)                                 | Vocabulary platform (wajib dibaca pertama)                |
| 03  | [Arsitektur](docs/03-arsitektur.md)                               | Konteks, modul, aturan dependensi, struktur folder, stack |
| 04  | [Model data](docs/04-model-data.md)                               | ERD + spesifikasi tabel + strategi index                  |
| 05  | [Keamanan & akses](docs/05-keamanan-dan-akses.md)                 | Otorisasi, UU PDP, threat model                           |
| 06  | [Metadata & runtime](docs/06-metadata-dan-runtime.md)             | Field type, validasi, form renderer, versioning           |
| 07  | [Processing & indikator](docs/07-processing-dan-indikator.md)     | DSL pipeline, formula, indikator, snapshot                |
| 08  | [Workflow & event](docs/08-workflow-dan-event.md)                 | State machine, outbox, katalog event                      |
| 09  | [Integrasi, API & SPLP](docs/09-integrasi-api-splp.md)            | REST API, data product, kesiapan SPLP                     |
| 10  | [Pentahelix](docs/10-pentahelix.md)                               | Aktor, isu, kontribusi, outcome, traceability             |
| 11  | [Frontend & aksesibilitas](docs/11-frontend-dan-aksesibilitas.md) | Inertia/React, design system, WCAG 2.2 AA                 |
| 12  | [Roadmap & milestone](docs/12-roadmap-dan-milestone.md)           | M0–M14, acceptance criteria, MVP                          |
| 13  | [NFR & operasional](docs/13-nfr-dan-operasional.md)               | Target performa, backup, observability, deployment        |
| 14  | [Regulasi](docs/14-regulasi.md)                                   | Pemetaan dasar hukum + status verifikasi                  |
| 15  | [Risiko](docs/15-risiko.md)                                       | Risk register dan mitigasi                                |
| ADR | [docs/adr/](docs/adr/)                                            | Architecture Decision Records                             |

## Urutan baca yang disarankan

1. Glosarium → Visi → Arsitektur → Model data
2. ADR 0004 (storage), 0007 (otorisasi), 0008 (processing): tiga keputusan paling berisiko
3. Roadmap: apa yang dikerjakan lebih dulu
