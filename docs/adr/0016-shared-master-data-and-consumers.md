# ADR 0016 — Master Data Bersama, Persetujuan Consumer, dan Perlakuan NIK

- **Status:** Diterima
- **Tanggal:** 2026-09-30
- **Terkait:** ADR 0004, ADR 0005, ADR 0007, ADR 0015, docs/04 §3 & §5, docs/05 §2–§3, Perpres 39/2019 (Satu Data Indonesia), UU 27/2022 (PDP)

## Konteks

Sampai M3, entity bersama (`is_shared`) dan entity Core (organisasi) bisa langsung dirujuk aplikasi
mana pun. Konsekuensinya:

1. Tidak ada jejak siapa yang memakai data induk dan untuk apa. Ini bertentangan dengan peran
   Walidata dalam Satu Data Indonesia.
2. Relasi ke data pribadi (orang/pegawai) bisa dibuat tanpa klasifikasi yang benar, sehingga judul
   target (nama) keluar tanpa `FieldGate`.
3. Master data orang membutuhkan NIK untuk mencari persis dan mencegah data ganda, tetapi NIK
   adalah data pribadi spesifik.

## Keputusan

1. **Consumer wajib disetujui.** Aplikasi hanya boleh merujuk entity milik aplikasi lain, termasuk
   master data Core, bila ada baris `entity_consumers` berstatus `approved`. Aturan ini ditegakkan
   di tiga titik: saat menyimpan field draft, saat publikasi (`DraftValidator`), dan saat runtime
   (lookup dan validasi tautan di `RelationTargets`).
2. **Pemisahan tugas.** Admin platform (`platform.metadata.manage`) mengajukan dengan alasan.
   Walidata (`data_steward`, `platform.consumer.approve`) menyetujui, menolak (wajib beralasan),
   atau mencabut. Pencabutan tidak menghapus tautan lama. Tautan baru dan publikasi relasi baru
   ditolak.
3. **Hak baca consumer `reference`.** User aplikasi consumer yang tidak punya permission `view` di
   aplikasi pemilik tetap bisa memilih target lewat **visibilitas saja** (publik/internal), tanpa
   grant unit. Pola ini sama dengan entity Core. Data `private`/`restricted` pemilik tetap tertutup.
4. **Relasi ke Core `person`/`employee` wajib berklasifikasi pribadi** (`personal` atau
   `personal_specific`). Dengan begitu `FieldGate` dan gate Pejabat PDP berlaku untuk nama yang
   tampil.
5. **NIK tidak pernah plaintext.**
    - `nik_hash` = HMAC-SHA256 dengan pepper `BARA_NIK_PEPPER`, dipakai untuk cari persis dan
      mencegah ganda.
    - `nik_enc` = AES-256-GCM dengan kunci `BARA_PII_KEY`. Kunci ini terpisah dari `APP_KEY`
      sehingga bocornya kunci sesi/cookie tidak membuka NIK.
    - `nik_last4` untuk tampilan tersamar.
6. **Membuka NIK** membutuhkan permission `platform.pii.reveal` (hanya Walidata), konfirmasi kata
   sandi ulang, dan alasan. Setiap pembukaan dicatat `pii.revealed` di audit dan outbox, tanpa
   nilai NIK. Respons berupa halaman, bukan redirect, sehingga NIK tidak pernah masuk sesi/flash.
   Pencarian NIK memakai POST sehingga NIK tidak masuk URL, riwayat browser, maupun log akses.
7. **Wilayah** diimpor dari berkas Kepmendagri (`bara:import-regions --source-ref=…`). Impor
   bersifat idempoten: kode yang hilang dari sumber dinonaktifkan (`valid_to`), tidak dihapus.

## Alternatif

| Opsi                                     | Kekurangan                                                                   |
| ---------------------------------------- | ---------------------------------------------------------------------------- |
| Consumer cukup dicatat, tanpa gate       | Pencatatan bisa dilewati; tidak ada kontrol Walidata                         |
| Consumer memberi permission `view` penuh | Data unit lain milik pemilik terbuka lewat grant, melebihi kebutuhan merujuk |
| NIK dienkripsi dengan `APP_KEY`          | Rotasi/bocornya kunci aplikasi langsung membuka NIK                          |
| Enkripsi deterministik untuk pencarian   | Membocorkan kesamaan NIK antarbaris tanpa kunci terpisah; HMAC lebih jelas   |

## Konsekuensi

- Relasi lintas aplikasi yang sudah terbit sebelum M4 disetujui otomatis oleh migrasi
  (grandfathering, `requested_by` kosong).
- Kehilangan `BARA_PII_KEY` berarti NIK terenkripsi tidak bisa dibuka lagi. Mengganti
  `BARA_NIK_PEPPER` berarti semua `nik_hash` harus dihitung ulang. Keduanya wajib disimpan di
  secret manager (docs/13).
- Walidata menjadi role wajib di setiap instalasi (`bara:assign-role … data_steward … --force`).
