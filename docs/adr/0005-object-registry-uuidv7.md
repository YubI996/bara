# ADR 0005 — Object Registry dan UUIDv7

- **Status:** Diterima
- **Tanggal:** 2026-09-25

## Konteks
Relasi, audit, workflow, event, dan scope akses harus bisa menunjuk objek apa pun, baik record JSONB maupun baris Core fisik. ID juga harus unik global untuk pertukaran lewat SPLP.

## Keputusan
- Tabel `objects` sebagai supertype (class-table inheritance). `records.id` dan `core_*.id` adalah FK ke `objects.id`.
- `objects` menyimpan `entity_id`, `owner_org_id`, `owner_path` (ltree), `visibility`. Filter scope cukup dilakukan di satu tempat.
- Primary key memakai `uuidv7()` (native PostgreSQL 18): terurut waktu, ramah B-tree, dan tidak bisa ditebak berurutan.
- ID eksternal: URN `urn:bara:{kode_wilayah_pemda}:{entity}:{uuid}`.

## Alternatif
| Opsi | Kekurangan |
|---|---|
| Bigint auto-increment | Bisa ditebak (IDOR lebih mudah dieksploitasi), bentrok saat federasi |
| UUIDv4 | Fragmentasi index |
| Relasi polimorfik (`target_type`, `target_id`) | Tanpa FK |

## Konsekuensi
- (+) Satu FK untuk semua relasi, satu filter scope untuk semua data.
- (−) Setiap insert menulis dua baris (objects + records). Overhead ini kecil dan terjadi dalam satu transaksi.
- (−) Bootstrap melingkar (doc 04 §2) diselesaikan dengan FK `DEFERRABLE`.
