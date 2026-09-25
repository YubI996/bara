# ADR 0008 — Processing: DSL JSON Deklaratif Dikompilasi ke SQL

- **Status:** Diterima
- **Tanggal:** 2026-09-25
- **Terkait:** doc 07

## Konteks

Pengguna admin perlu mendefinisikan pengolahan (filter, join, group, formula) tanpa menulis kode, tetapi tanpa membuka celah SQL injection atau eksekusi kode.

## Keputusan

- Process = DSL JSON (divalidasi JSON Schema) → AST → type check terhadap metadata → Query Builder (CTE berantai) dengan binding.
- Formula memakai **parser Pratt buatan sendiri** dengan fungsi whitelist. Keluarannya SQL (untuk process) atau evaluator PHP/TS (untuk satu record: guard, `visible_when`).
- **Tidak memakai** `eval`, `symfony/expression-language`, raw SQL dari pengguna, atau visual node editor (sampai setelah R2).
- Eksekusi dengan `statement_timeout`, EXPLAIN guard, queue terpisah.

## Alternatif

| Opsi                           | Kekurangan                                                          |
| ------------------------------ | ------------------------------------------------------------------- |
| SQL mentah oleh admin          | Injeksi, bypass scope, tidak portabel                               |
| symfony/expression-language    | Evaluasi di PHP (lambat untuk data besar), akses objek terlalu luas |
| Pemrosesan di PHP (collection) | Memori & waktu tidak skalabel                                       |
| dbt/engine eksternal           | Komponen tambahan, tidak terintegrasi scope akses                   |

## Konsekuensi

- (+) Aman by construction, cepat (PostgreSQL mengerjakan agregasi), dan dapat diuji (property test SQL = PHP).
- (−) Parser & compiler harus dirawat sendiri (±2.000 baris). Setiap step baru wajib lewat ADR.
