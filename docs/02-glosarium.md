# 02 — Glosarium

Istilah di bawah dipakai **persis** sama di kode, database, UI admin, dan dokumen.
Prinsip _Semantics Matter_: kesamaan nama belum berarti kesamaan konsep. Kalau ragu, tambahkan istilah baru ke sini dulu sebelum membuat entity.

## A. Metadata (definisi aplikasi)

| Istilah            | Definisi                                                                                                        | Tabel                         |
| ------------------ | --------------------------------------------------------------------------------------------------------------- | ----------------------------- |
| **Application**    | Kumpulan entity, form, view, workflow, dan menu yang membentuk satu sistem informasi. Punya owner organization. | `applications`                |
| **Entity**         | Tipe objek bisnis (mis. `Program`, `Realization`). Punya _storage type_.                                        | `entities`                    |
| **Entity Version** | Snapshot skema entity yang tidak berubah setelah dipublikasikan. Record selalu menunjuk ke versi tertentu.      | `entity_versions`             |
| **Field**          | Atribut entity dengan tipe, validasi, klasifikasi data.                                                         | `fields`                      |
| **Relationship**   | Hubungan bernama antar-entity (`many_to_one`, `many_to_many`), dengan aturan hapus.                             | `relationships`               |
| **Storage Type**   | `physical` (tabel sendiri, dipakai Core) atau `document` (JSONB di `records`).                                  | kolom `entities.storage_type` |
| **System Entity**  | Entity milik platform (Pentahelix, dsb.). Skema terkunci, hanya boleh ditambah _extension field_.               | `entities.is_system`          |
| **Form**           | Tata letak input untuk satu entity (section, urutan, kondisi tampil).                                           | `forms`                       |
| **View**           | Cara menampilkan data: `table`, `detail`, `kpi`, `bar`, `line`, `pie`, `stacked`.                               | `views`                       |
| **Dashboard**      | Komposisi beberapa View dalam grid. Tidak menyimpan logika.                                                     | `dashboards`                  |
| **Action**         | Operasi yang bisa dipicu pengguna: CRUD, transisi workflow, export.                                             | `actions`                     |

## B. Data

| Istilah                       | Definisi                                                                                                                  |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| **Object**                    | Semua hal yang punya identitas di platform (record, organisasi, region). Terdaftar di `objects`.                          |
| **Record**                    | Instance dari entity bertipe `document`. Disimpan di `records.data` (JSONB).                                              |
| **Core Entity**               | Master data bersama (`Core.Organization`, `Core.Region`, `Core.Person`, `Core.Employee`, `Core.FiscalYear`). Tabel fisik. |
| **Master Data**               | Data referensi yang relatif stabil dan dipakai lintas aplikasi. Selalu punya **owner**.                                   |
| **Reference Data / Codelist** | Daftar kode terkontrol (status, kategori, kode wilayah Kemendagri, klasifikasi urusan).                                   |
| **Operational Data**          | Data transaksi dari aplikasi (realisasi, laporan, pengajuan).                                                             |
| **Derived Information**       | Hasil pengolahan (nilai indikator, agregat). **Tidak pernah menimpa data sumber.**                                        |
| **Owner**                     | Organization yang berwenang mengubah data dan bertanggung jawab atas kualitasnya.                                         |
| **Consumer**                  | Application yang membaca/mereferensikan data milik owner lain. Harus terdaftar.                                           |
| **Scope**                     | Node organisasi tempat data dimiliki. Menentukan siapa yang boleh melihat.                                                |
| **Visibility**                | `private` (scope saja), `internal` (semua ASN Pemda), `partner` (termasuk mitra Pentahelix terdaftar), `public`.          |
| **Classification**            | Sensitivitas field: `public`, `internal`, `restricted`, `personal`, `personal_specific` (UU PDP).                         |

## C. Identitas & akses

| Istilah             | Definisi                                                                                                              |
| ------------------- | --------------------------------------------------------------------------------------------------------------------- |
| **User**            | Akun login. Bisa ASN (terhubung ke `Core.Employee`) atau eksternal (terhubung ke `Core.Person` + Organization mitra). |
| **Organization**    | Semua badan: OPD, unit kerja, kampus, perusahaan, komunitas, media. Dibedakan lewat `sector`.                         |
| **Role**            | Kumpulan permission (mis. `operator`, `verifikator`, `app_admin`).                                                    |
| **Permission**      | Kode aksi: `{application}.{entity}.{action}` atau `platform.{area}.{action}`.                                         |
| **Role Assignment** | User + Role + scope organisasi + apakah berlaku ke unit di bawahnya.                                                  |
| **Clearance**       | Tingkat klasifikasi field tertinggi yang boleh dilihat role.                                                          |
| **API Client**      | Identitas mesin (aplikasi lain, SPLP) dengan scope API terbatas.                                                      |

## D. Proses & informasi

| Istilah             | Definisi                                                                                                                             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| **Process**         | Pipeline pengolahan deklaratif (DSL JSON) dari source ke output. Berversi.                                                           |
| **Process Step**    | Satu operasi dalam pipeline (`filter`, `join`, `group`, `aggregate`, `formula`, ...).                                                |
| **Process Run**     | Satu eksekusi process dengan parameter tertentu. Menyimpan lineage.                                                                  |
| **Formula**         | Ekspresi aritmetika/logika terbatas yang dikompilasi ke SQL. Bukan kode bebas.                                                       |
| **Indicator**       | Ukuran reusable: formula + dimensi + periode + satuan + owner + arah (naik baik/turun baik).                                         |
| **Indicator Value** | Nilai indikator hasil hitung untuk satu periode × satu kombinasi dimensi. Snapshot.                                                  |
| **Target**          | Nilai yang ingin dicapai indikator pada periode tertentu.                                                                            |
| **Dimension**       | Sumbu pemotong indikator (OPD, wilayah, tahun, sektor).                                                                              |
| **Data Product**    | Informasi yang dipublikasikan untuk konsumen lain dengan kontrak: schema, versi, owner, frekuensi, kebijakan akses, status kualitas. |

## E. Workflow & event

| Istilah                 | Definisi                                                                                              |
| ----------------------- | ----------------------------------------------------------------------------------------------------- |
| **Workflow Definition** | State machine yang melekat ke entity. Berversi.                                                       |
| **State**               | Posisi record dalam workflow (`draft`, `submitted`, `verified`, `approved`, `published`, `rejected`). |
| **Transition**          | Perpindahan state yang dipicu Action, dijaga permission dan guard.                                    |
| **Workflow Instance**   | Status workflow satu record.                                                                          |
| **Domain Event**        | Fakta bisnis yang sudah terjadi (bentuk lampau): `record.created`, `record.approved`.                 |
| **Outbox**              | Tabel penampung event yang ditulis dalam transaksi yang sama dengan perubahan data.                   |
| **Subscription**        | Aturan "jika event X maka lakukan Y" (hitung ulang indikator, notifikasi, webhook).                   |

## F. Pentahelix

| Istilah          | Definisi                                                                                                |
| ---------------- | ------------------------------------------------------------------------------------------------------- |
| **Sector**       | `government`, `academia`, `business`, `community`, `media`.                                             |
| **Issue**        | Masalah publik yang menjadi konteks kolaborasi (mis. "Pengurangan Sampah Plastik").                     |
| **Program**      | Rangkaian kegiatan terencana untuk menangani issue. Bisa lintas organisasi.                             |
| **Activity**     | Kegiatan konkret di bawah program.                                                                      |
| **Contribution** | Apa yang disumbangkan satu organisasi ke program/activity (regulasi, riset, dana, fasilitas, kampanye). |
| **Partnership**  | Kesepakatan formal antarorganisasi (MoU/PKS) dengan periode.                                            |
| **Evidence**     | Bukti (dokumen, foto, dataset) yang menopang contribution/outcome.                                      |
| **Outcome**      | Perubahan yang dihasilkan, diukur lewat satu atau lebih indicator.                                      |

## G. Istilah regulasi (padanan)

| Istilah platform | Padanan regulasi                                      |
| ---------------- | ----------------------------------------------------- |
| Owner (data)     | Produsen Data (Perpres 39/2019)                       |
| Platform owner   | Walidata (Perpres 39/2019)                            |
| Codelist         | Kode Referensi (Perpres 39/2019)                      |
| Core Entity      | Data Induk (Perpres 39/2019)                          |
| Controller       | Pengendali Data Pribadi (UU 27/2022)                  |
| DPO              | Pejabat/petugas Pelindungan Data Pribadi (UU 27/2022) |
