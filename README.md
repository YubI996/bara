# BARA — Platform Sistem Informasi Terintegrasi & Pentahelix

Platform **metadata-driven** untuk satu Pemerintah Daerah: sistem informasi baru dibuat dari
definisi (entity, field, relasi, proses, indikator, view, workflow). Tidak lagi dari kode baru.
Semua aplikasi berdiri di atas identitas, master data, semantik, dan pengolahan yang sama.
Platform ini disiapkan untuk terhubung ke **SPLP** (Sistem Penghubung Layanan Pemerintah)
supaya bisa bertukar data antar-Pemda.

> Ukuran keberhasilan: **seberapa sedikit konsep, data, relasi, formula, dan proses yang harus
> didefinisikan ulang** saat membuat aplikasi baru.

## Status

**Fase perencanaan selesai (v1.0 dokumen).** Implementasi belum dimulai. Langkah berikutnya
adalah M0 (Fondasi) di [roadmap](docs/12-roadmap-dan-milestone.md).

## Keputusan utama (ringkas)

| Aspek | Keputusan | ADR |
|---|---|---|
| Bentuk sistem | Modular monolith, satu deployment | [0001](docs/adr/0001-modular-monolith.md) |
| Backend | Laravel 13, PHP 8.5, strict types | [0001](docs/adr/0001-modular-monolith.md) |
| Frontend | Inertia v3 + React 19 + TypeScript strict + Tailwind v4 | [0002](docs/adr/0002-frontend-inertia-react.md) |
| Database | PostgreSQL 18 (JSONB, ltree, PostGIS, RLS) | [0003](docs/adr/0003-postgresql.md) |
| Penyimpanan record | Hybrid: Core = tabel fisik, aplikasi = JSONB, relasi = `record_links` | [0004](docs/adr/0004-hybrid-storage.md) |
| Identitas objek | UUIDv7 + registry `objects` (supertype) | [0005](docs/adr/0005-object-registry-uuidv7.md) |
| Evolusi skema | Metadata berversi (draft → published) | [0006](docs/adr/0006-metadata-versioning.md) |
| Otorisasi | RBAC + scope organisasi (ltree) + field classification + RLS | [0007](docs/adr/0007-authorization-model.md) |
| Processing | DSL JSON deklaratif → SQL (query builder), formula via parser sendiri | [0008](docs/adr/0008-processing-dsl.md) |
| Indikator | Definisi berversi + snapshot nilai + lineage | [0009](docs/adr/0009-indicator-snapshot.md) |
| Event | Transactional outbox + listener idempotent | [0010](docs/adr/0010-transactional-outbox.md) |
| Audit | Append-only, dipartisi per bulan | [0011](docs/adr/0011-audit-append-only.md) |
| Multi-Pemda | Single-tenant; federasi lewat SPLP, bukan database bersama | [0012](docs/adr/0012-single-tenant-splp-federation.md) |
| Auth | Lokal + siap OIDC SSO; API mesin via OAuth2 client credentials | [0013](docs/adr/0013-authentication.md) |

## Peta dokumen

| # | Dokumen | Isi |
|---|---|---|
| 00 | [Rencana awal](docs/00-rencana-awal.md) | Dokumen sumber (tidak diubah) |
| 01 | [Visi & ruang lingkup](docs/01-visi-dan-ruang-lingkup.md) | Tujuan, batas, stakeholder, metrik sukses |
| 02 | [Glosarium](docs/02-glosarium.md) | Vocabulary platform (wajib dibaca pertama) |
| 03 | [Arsitektur](docs/03-arsitektur.md) | Konteks, modul, aturan dependensi, struktur folder, stack |
| 04 | [Model data](docs/04-model-data.md) | ERD + spesifikasi tabel + strategi index |
| 05 | [Keamanan & akses](docs/05-keamanan-dan-akses.md) | Otorisasi, UU PDP, threat model |
| 06 | [Metadata & runtime](docs/06-metadata-dan-runtime.md) | Field type, validasi, form renderer, versioning |
| 07 | [Processing & indikator](docs/07-processing-dan-indikator.md) | DSL pipeline, formula, indikator, snapshot |
| 08 | [Workflow & event](docs/08-workflow-dan-event.md) | State machine, outbox, katalog event |
| 09 | [Integrasi, API & SPLP](docs/09-integrasi-api-splp.md) | REST API, data product, kesiapan SPLP |
| 10 | [Pentahelix](docs/10-pentahelix.md) | Aktor, isu, kontribusi, outcome, traceability |
| 11 | [Frontend & aksesibilitas](docs/11-frontend-dan-aksesibilitas.md) | Inertia/React, design system, WCAG 2.2 AA |
| 12 | [Roadmap & milestone](docs/12-roadmap-dan-milestone.md) | M0–M14, acceptance criteria, MVP |
| 13 | [NFR & operasional](docs/13-nfr-dan-operasional.md) | Target performa, backup, observability, deployment |
| 14 | [Regulasi](docs/14-regulasi.md) | Pemetaan dasar hukum + status verifikasi |
| 15 | [Risiko](docs/15-risiko.md) | Risk register dan mitigasi |
| ADR | [docs/adr/](docs/adr/) | Architecture Decision Records |

## Urutan baca yang disarankan

1. Glosarium → Visi → Arsitektur → Model data
2. ADR 0004 (storage), 0007 (otorisasi), 0008 (processing): tiga keputusan paling berisiko
3. Roadmap: apa yang dikerjakan lebih dulu
