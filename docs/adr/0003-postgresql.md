# ADR 0003 — PostgreSQL 18 sebagai Database Tunggal

- **Status:** Diterima
- **Tanggal:** 2026-09-25

## Konteks
Butuh dokumen fleksibel (record dinamis), hierarki organisasi, geospasial, full-text, keamanan baris, dan agregasi analitik dalam satu sistem. Lingkungan dev pengguna memakai MySQL (Laragon).

## Keputusan
PostgreSQL 18.x dengan extension `ltree`, `pg_trgm`, `pgcrypto`, `citext`, `postgis` (M14). Fitur yang dipakai: JSONB + index ekspresi, `uuidv7()` native, RLS, partisi (audit), `FOR UPDATE SKIP LOCKED` (outbox), window & ordered-set aggregate (processing).

## Alternatif
| Opsi | Kelebihan | Kekurangan |
|---|---|---|
| MySQL 8 | Familiar di Laragon | JSON index terbatas (hanya generated column), tanpa RLS, tanpa ltree, geospasial lebih lemah |
| PostgreSQL + ClickHouse | Analitik sangat cepat | Kompleksitas operasional; belum dibutuhkan pada skala doc 01 §6 |

## Konsekuensi
- (+) Satu mesin untuk OLTP + analitik ringan.
- (−) Tim perlu belajar fitur khusus PostgreSQL (ltree, RLS, JSONB operator). Lingkungan Windows butuh instalasi PostgreSQL + PostGIS (doc 03 §6).
- Kalau analitik tumbuh besar, ekspor ke gudang analitik lewat data product (bukan akses langsung).
