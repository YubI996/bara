# ADR 0006 — Metadata Berversi (Draft → Published → Superseded)

- **Status:** Diterima
- **Tanggal:** 2026-09-25
- **Terkait:** doc 06 §2, §4

## Konteks
Kalau skema entity berubah saat data sudah ada, record lama, process, dan indikator bisa rusak diam-diam.

## Keputusan
- Entity, workflow, process, dan indicator memiliki tabel versi. Hanya satu `draft` dan satu `published` aktif per objek.
- Versi `published` bersifat immutable dan menyimpan `compiled_schema`.
- Field punya `field_key` (UUID stabil), sehingga rename `code` tidak memutus data.
- Publish menjalankan diff + klasifikasi perubahan + dependency check (`metadata_dependencies`). Perubahan destruktif ditolak jika masih dipakai.
- Record menyimpan `entity_version_id` saat terakhir disimpan.

## Konsekuensi
- (+) Cache `compiled_schema` tanpa invalidasi, dan indikator historis tetap bisa direproduksi.
- (−) UI admin lebih kompleks (draft vs published, diff).
