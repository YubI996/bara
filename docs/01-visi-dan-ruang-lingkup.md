# 01 — Visi & Ruang Lingkup

## 1. Masalah yang diselesaikan

Di Pemda, setiap OPD biasanya membangun aplikasi sendiri. Akibatnya:

- master data (OPD, wilayah, pegawai, program) diduplikasi dan saling tidak cocok,
- integrasi dibuat point-to-point, rapuh, dan mahal dirawat,
- indikator dihitung ulang dengan formula berbeda-beda, sehingga angka kinerja tidak konsisten,
- data kolaborasi dengan akademisi, dunia usaha, komunitas, dan media tersebar di dokumen, tidak tertelusuri.

## 2. Visi

> **Aplikasi adalah komposisi yang dapat berubah. Data, identitas, relasi, proses, ownership, dan
> semantik adalah aset platform yang harus dapat digunakan ulang.**

## 3. Ruang lingkup

### Termasuk (in scope)

| Area               | Cakupan                                                                                            |
| ------------------ | -------------------------------------------------------------------------------------------------- |
| Metadata engine    | Application, Entity, Field, Relationship berversi                                                  |
| Runtime            | CRUD dan form yang dihasilkan dari metadata, validasi server-side                                  |
| Shared data        | Core entity (Organization, Region, Person, Employee, FiscalYear) dengan owner                      |
| Akses              | RBAC + scope hierarki organisasi + klasifikasi field (UU PDP)                                      |
| Workflow           | State machine yang dapat dikonfigurasi per entity                                                  |
| Processing         | Pipeline deklaratif: filter, join, group, aggregate, formula, classify, statistik dasar            |
| Indikator          | Definisi reusable, target, snapshot nilai per periode                                              |
| Presentasi         | Table, KPI, bar, line, pie/stacked, dashboard komposit                                             |
| Event              | Domain event lewat outbox, notifikasi, webhook                                                     |
| Pentahelix         | Organization lintas sektor, Issue, Program, Activity, Contribution, Partnership, Evidence, Outcome |
| API & data product | REST API berversi, OpenAPI 3.1, data product dengan access policy                                  |
| SPLP               | Kesiapan kontrak API dan autentikasi mesin untuk registrasi ke SPLP                                |

### Tidak termasuk (non-goals, setidaknya sampai M14)

- **Visual drag-and-drop builder.** Konfigurasi lewat form admin terstruktur dulu. Visual builder adalah lapisan UI di atas engine yang sudah stabil.
- **Multi-tenant dalam satu database.** Multi-Pemda dilakukan lewat federasi SPLP ([ADR 0012](adr/0012-single-tenant-splp-federation.md)).
- **Microservices.**
- **Menggantikan aplikasi SPBE Prioritas nasional** (Perpres 82/2023). Platform ini mengonsumsi dan menyediakan data, tidak menduplikasi aplikasi nasional.
- **Custom code per aplikasi.** Kebutuhan yang tidak bisa dipenuhi metadata dicatat sebagai _extension point_ terdokumentasi, bukan di-hack.
- **Forecasting dan ML** sebelum M14.

## 4. Stakeholder

| Stakeholder                          | Kepentingan                                        | Peran di platform                                          |
| ------------------------------------ | -------------------------------------------------- | ---------------------------------------------------------- |
| Diskominfo (Walidata/pengelola SPBE) | Integrasi, keamanan, Satu Data                     | Platform owner, super admin, owner data product lintas OPD |
| Bappeda                              | Perencanaan, monitoring program, indikator kinerja | Owner Program/Indikator (MVP1)                             |
| OPD (Produsen Data)                  | Input data sektoral, laporan                       | App admin, operator, verifikator                           |
| Pimpinan daerah                      | Dashboard eksekutif                                | Viewer dashboard                                           |
| Akademisi                            | Riset, dataset                                     | Contributor eksternal (Pentahelix)                         |
| Dunia usaha                          | CSR, fasilitas, kemitraan                          | Contributor eksternal                                      |
| Komunitas                            | Program partisipatif                               | Contributor eksternal                                      |
| Media                                | Kampanye publik, publikasi                         | Contributor eksternal, konsumen data publik                |
| Pemda lain / K/L                     | Pertukaran data                                    | Konsumen/penyedia via SPLP                                 |
| Masyarakat                           | Data terbuka                                       | Konsumen data product publik                               |

## 5. Metrik keberhasilan

| Metrik                           | Cara ukur                                                                           | Target MVP2                   |
| -------------------------------- | ----------------------------------------------------------------------------------- | ----------------------------- |
| **Reuse ratio**                  | (entity/indikator/proses yang direferensikan ulang) / (total yang dipakai app baru) | ≥ 60% untuk aplikasi kedua    |
| Duplikasi master data            | Jumlah tabel/entity master di luar `Core` yang maknanya sama dengan Core            | 0                             |
| Integrasi point-to-point baru    | Integrasi di luar event/API/data product                                            | 0                             |
| Waktu membuat app CRUD sederhana | Dari definisi entity sampai bisa dipakai operator                                   | ≤ 1 hari kerja (tanpa deploy) |
| Konsistensi indikator            | Satu indikator hanya punya satu definisi aktif per versi                            | 100%                          |
| Aksesibilitas                    | Audit WCAG 2.2 AA otomatis + manual pada halaman runtime                            | 0 pelanggaran level A/AA      |
| Auditability                     | Aksi penting yang tercatat di audit log                                             | 100% (lihat daftar di doc 05) |

## 6. Asumsi skala (perlu divalidasi dengan data Pemda)

| Parameter                 | Asumsi awal                                    |
| ------------------------- | ---------------------------------------------- |
| Jumlah OPD + unit kerja   | 50–150 node organisasi, kedalaman hierarki ≤ 6 |
| Pengguna terdaftar        | 2.000–5.000 (ASN + eksternal)                  |
| Pengguna konkuren puncak  | 200–500 (akhir periode pelaporan)              |
| Record per 5 tahun        | 1–10 juta total, ≤ 2 juta per entity           |
| Aplikasi di atas platform | 5 di tahun pertama, 20+ di tahun ketiga        |
| Tim pengembang            | 2–4 developer + 1 analis                       |

Asumsi ini menentukan keputusan performa di [doc 13](13-nfr-dan-operasional.md). Kalau data riil jauh lebih besar (misalnya data kependudukan per jiwa), ADR 0004 punya jalur promosi ke tabel fisik.
