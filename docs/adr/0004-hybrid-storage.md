# ADR 0004 — Penyimpanan Hybrid: Core Fisik, Aplikasi JSONB, Relasi di record_links

- **Status:** Diterima
- **Tanggal:** 2026-09-25
- **Terkait:** doc 04 §2, §4; ADR 0005

## Konteks

Record entity dinamis harus bisa disimpan tanpa migration per entity, tetapi tetap cepat difilter/agregasi dan relasinya punya integritas referensial.

## Keputusan

1. **Core entity** (Organization, Region, Person, Employee, FiscalYear) memakai tabel fisik biasa.
2. **Entity aplikasi** (`storage_type = document`) disimpan di satu tabel `records` dengan kolom `data jsonb`.
3. **Relasi tidak disimpan di JSONB.** Relasi disimpan di `record_links(source_id → objects, target_id → objects)`, sehingga FK dijaga database.
4. Field yang ditandai `is_indexed` mendapat index ekspresi partial per entity, dibuat oleh job dengan nama hash (maks. 8 per entity).
5. Jalur **promosi** entity panas ke tabel fisik lewat migration yang di-review (doc 04 §4). Kontrak `RecordRepository` tetap.

## Alternatif

| Opsi                                 | Kelebihan                     | Kekurangan                                                                         |
| ------------------------------------ | ----------------------------- | ---------------------------------------------------------------------------------- |
| EAV                                  | Skema sepenuhnya generik      | Agregasi sangat lambat, query tidak terbaca, tipe lemah                            |
| Tabel fisik per entity (DDL runtime) | Performa & constraint terbaik | DDL dari UI berisiko (lock, rollback, keamanan identifier), migration tak terlacak |
| JSONB penuh termasuk relasi          | Paling sederhana              | Tidak ada FK, navigasi balik lambat, orphan data                                   |

## Konsekuensi

- (+) Entity baru tanpa deploy. Relasi dan integritas tetap dijaga database.
- (−) Tabel `records` bisa sangat besar. Pantau ukuran, dan pertimbangkan partisi `HASH(entity_id)` bila > 20 juta baris.
- (−) Query harus melalui compiler/repository, bukan Eloquent biasa.
