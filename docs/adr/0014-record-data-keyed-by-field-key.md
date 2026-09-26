# ADR 0014 — Data Record Disimpan dengan Kunci `field_key`, Bukan `code`

- **Status:** Diterima
- **Tanggal:** 2026-09-26
- **Terkait:** ADR 0004, ADR 0006, docs/04 §4, docs/06 §4

## Konteks

Dokumen awal menyimpan `records.data` sebagai `{field_code: value}`. Konsekuensinya:

1. Mengganti kode field butuh job yang menulis ulang kunci JSONB di jutaan baris.
2. Menghapus field `x`, lalu menambah field baru bernama `x` dengan tipe lain, membuat data lama dan baru **bertabrakan** di kunci yang sama. Contoh: teks lama terbaca sebagai angka baru.
3. Index ekspresi (`data->>'kode'`) rusak begitu kode diganti.

## Keputusan

- `records.data` memakai **`field_key` (UUID stabil) sebagai kunci**: `{"<field_key>": value}`.
- Kode field hanya dipakai di batas luar: form, API, DSL processing. Keduanya dipetakan lewat metadata versi aktif.
- Index ekspresi: `(data->>'<field_key>')`. Rename kode tidak menyentuh data maupun index.
- Field relasi tetap tidak disimpan di JSONB (ADR 0004). Nilainya ada di `record_links`.

## Alternatif

| Opsi                           | Kekurangan                                                     |
| ------------------------------ | -------------------------------------------------------------- |
| Kunci `code` + job rewrite     | Mahal, rawan setengah jalan, dan tabrakan kode tetap mungkin   |
| Kunci `code` + larangan rename | Membatasi admin. Tabrakan saat hapus lalu tambah tetap terjadi |

## Konsekuensi

- (+) Rename aman tanpa migrasi. Data field terhapus tetap ada dan tidak pernah tertimpa.
- (−) JSON mentah kurang terbaca manusia. Solusinya: alat debug dan API selalu memetakan ke kode.
- (−) Query ad-hoc manual ke DB butuh pemetaan field_key. Perlu view bantu per entity (menyusul bila diperlukan).
