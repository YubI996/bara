# ADR 0009 — Indikator Berversi dengan Snapshot Nilai dan Lineage

- **Status:** Diterima
- **Tanggal:** 2026-09-25

## Konteks
Dashboard yang menghitung ulang setiap dibuka menjadi lambat. Selain itu, kalau formula atau data berubah, angka yang sudah dilaporkan ikut berubah diam-diam.

## Keputusan
- `indicator_values` menyimpan snapshot per (versi, periode, dimensi) dan menunjuk ke `process_runs` (lineage).
- Hitung ulang dipicu event (debounce) atau jadwal. Periode yang sudah `is_final` tidak dihitung ulang.
- Perubahan formula → `indicator_version` baru. Nilai versi lama dipertahankan.
- Dashboard hanya membaca snapshot.

## Konsekuensi
- (+) Dashboard cepat, angka bisa diaudit ("asal angka ini").
- (−) Ada jeda kesegaran (target ≤ 10 menit). UI menampilkan `computed_at`.
