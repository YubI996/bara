# 14 — Pemetaan Regulasi

> Dokumen ini adalah **pemetaan teknis**, bukan nasihat hukum. Status diperiksa **September 2026**.
> Kolom *Verifikasi* menunjukkan apakah nomor dan isi sudah dicek ke sumber primer (JDIH).
> Sebelum dipakai dalam dokumen resmi (KAK, kajian, SK), cek ulang ke JDIH BPK / JDIH instansi terkait.

| Regulasi | Pokok yang relevan | Dampak ke desain | Verifikasi |
|---|---|---|---|
| **Perpres 95/2018** tentang Sistem Pemerintahan Berbasis Elektronik | Definisi SPBE, Infrastruktur SPBE Nasional termasuk **Sistem Penghubung Layanan Pemerintah**, aplikasi umum & khusus, integrasi layanan | Integrasi antar-instansi lewat SPLP (ADR 0012), API by default | Definisi SPLP di Pasal 1 terkonfirmasi lewat sumber sekunder. Pasal rinci tentang infrastruktur nasional belum dicek ke teks. |
| **Perpres 82/2023** tentang Percepatan Transformasi Digital dan Keterpaduan Layanan Digital Nasional (ditetapkan 18 Des 2023) | Aplikasi SPBE Prioritas (pendidikan, kesehatan, bansos, adminduk, keuangan, administrasi pemerintahan, portal pelayanan publik, Satu Data, kepolisian), interoperabilitas, Digital ID | Jangan menduplikasi aplikasi prioritas nasional; siapkan konsumsi data via SPLP | Pokok terkonfirmasi. Belum ditemukan pengganti per Sep 2026. |
| **Perpres 132/2022** tentang Arsitektur SPBE Nasional | Referensi arsitektur (proses bisnis, data, aplikasi, infrastruktur, keamanan, layanan) | Entity metadata bisa diberi tag domain arsitektur SPBE untuk pelaporan | Belum diverifikasi ulang |
| **Perpres 39/2019** tentang Satu Data Indonesia | Prinsip: standar data, metadata, interoperabilitas, kode referensi & data induk; peran Walidata & Produsen Data | Owner = Produsen Data, `data_steward` = Walidata, codelist = kode referensi, Core = data induk | Pokok mapan. Nomor pasal prinsip belum dicek. |
| **UU 27/2022** tentang Pelindungan Data Pribadi | Data pribadi spesifik vs umum, dasar pemrosesan, DPIA, pejabat PDP, notifikasi kegagalan 3 × 24 jam, hak subjek data | `classification`, gate DPO, DSR, runbook insiden (doc 05 §3, doc 13 §7) | Nomor pasal di doc 05 dari pengetahuan umum, **perlu dicek ke teks** |
| **PP 33/2026** tentang Peraturan Pelaksanaan UU 27/2022 (diundangkan 16 Jul 2026, berlaku 16 Jan 2027) | Mekanisme hak subjek data, kewajiban pengendali & prosesor, DPIA, pejabat PDP, notifikasi kegagalan | Target kepatuhan sebelum R1 dipakai produksi; perlu kajian pasal per pasal | Keberadaan & tanggal terkonfirmasi (sumber sekunder); isi belum dibaca |
| **PP 71/2019** tentang Penyelenggaraan Sistem dan Transaksi Elektronik | Penyelenggara Sistem Elektronik lingkup publik mengelola, memproses, dan menyimpan data di Indonesia | Hosting di pusat data Pemda/PDN (doc 13 §2) | Pokok mapan. Nomor pasal belum dicek. |
| **Kepmendagri 300.2.2-2430 Tahun 2025** tentang Pemberian dan Pemutakhiran Kode dan Data Wilayah Administrasi Pemerintahan dan Pulau | Kode wilayah terbaru, menggantikan Kepmendagri 100.1.1-6117 Tahun 2022 | Seed `core_regions` + `source_ref`; proses pembaruan saat ada Kepmendagri baru | Nomor dari repositori data sekunder; diluncurkan 15 Mei 2025 |
| **Peraturan BSSN** tentang pedoman manajemen keamanan informasi SPBE & standar teknis keamanan SPBE | Kontrol keamanan aplikasi & infrastruktur SPBE | Baseline doc 05 §6, audit keamanan sebelum R1 | **Nomor & tahun belum diverifikasi.** Cek JDIH BSSN. |
| **Regulasi teknis SPLP** (Permen/Kepmen Komdigi, pedoman onboarding) | Standar API, mekanisme registrasi, keamanan gateway | M13 | **Belum ditemukan dokumen publik.** Minta ke Diskominfo Provinsi/Komdigi. |
| **UU 23/2014** tentang Pemerintahan Daerah (jo. perubahannya) | Pembagian urusan pemerintahan | Codelist `urusan_pemerintahan` | Pokok mapan |
| **Regulasi perencanaan & SIPD** (Permendagri terkait perencanaan pembangunan daerah & SIPD) | Struktur program/kegiatan/sub-kegiatan, kode nomenklatur | MVP1: Program/Activity perlu dipetakan ke nomenklatur SIPD agar tidak bertentangan | **Perlu identifikasi regulasi berlaku** dengan Bappeda |

## Tindak lanjut hukum (sebelum R1)

1. Bagian Hukum Setda / DPO memverifikasi kolom yang belum terverifikasi.
2. Bappeda memetakan hierarki program ↔ nomenklatur SIPD terbaru.
3. Diskominfo menghubungi pengelola SPLP (Komdigi / Diskominfo Provinsi) untuk pedoman onboarding.
4. Susun DPIA untuk entity yang memuat data pribadi spesifik.
5. Pertimbangkan Peraturan Kepala Daerah tentang tata kelola platform (owner data, walidata, akses mitra Pentahelix).
