# ADR 0011 — Audit Log Append-Only, Partisi Bulanan

- **Status:** Diterima
- **Tanggal:** 2026-09-25

## Konteks

Aksi penting (doc 05 §5) harus bisa dipertanggungjawabkan dan tidak bisa diubah oleh aplikasi.

## Keputusan

- `audit_logs` dipartisi `RANGE (occurred_at)` per bulan. Partisi dibuat otomatis oleh scheduler 3 bulan ke depan.
- Role DB `bara_app` hanya punya `INSERT, SELECT` pada `audit_logs` (tanpa `UPDATE/DELETE`).
- `changes` menyimpan diff, dan field `personal` di-mask/di-hash.
- Opsional (diaktifkan bila diminta auditor): hash chain `prev_hash` per partisi untuk deteksi manipulasi.
- Retensi default 5 tahun (partisi lama dipindah ke arsip terenkripsi). Angka ini perlu dikonfirmasi dengan ketentuan kearsipan daerah.

## Konsekuensi

- (+) Tamper-resistant pada level aplikasi, dan query per periode cepat.
- (−) DBA/superuser tetap bisa mengubah data. Mitigasinya adalah ekspor berkala ke penyimpanan WORM bila dibutuhkan.
