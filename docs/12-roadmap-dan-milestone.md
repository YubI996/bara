# 12 — Roadmap & Milestone

## 1. Perubahan dari rencana awal

| Rencana awal | Revisi | Alasan |
|---|---|---|
| Role & permission di M5 | **Scope & policy di M0–M2**, hardening (field clearance UI, RLS) di M5 | Scope organisasi memengaruhi setiap query sejak CRUD pertama |
| Audit implisit | **Audit sejak M0** | Mahal ditambahkan belakangan. Semua Action harus melewatinya. |
| Workflow di M9 (setelah dashboard) | **Workflow di M6**, sebelum processing | MVP1 butuh verifikasi realisasi sebelum dihitung jadi indikator |
| Program dibuat sebagai entity aplikasi MVP1 | **Program/Activity = system entity Collaboration sejak MVP1** | Supaya MVP2 lolos uji "tanpa definisi ulang" |
| Data product M13, API tersirat | API record di M2 (internal), data product + OpenAPI di M11 | API by default |
| – | **M13 SPLP** ditambahkan | Keputusan: Pemda tunggal, federasi via SPLP |

## 2. Milestone

Estimasi mengasumsikan tim **3 developer + 1 analis**, dalam satuan minggu kalender. Ini adalah **perkiraan kasar** yang perlu dikalibrasi setelah M0 selesai.

| M | Nama | Durasi | Bergantung | Produk |
|---|---|---|---|---|
| M0 | Fondasi | 3 mg | – | Repo, CI, auth, organisasi, object registry, audit, arch test |
| M1 | Metadata engine | 3 mg | M0 | App/entity/field/relationship + versi + publish |
| M2 | Runtime CRUD | 4 mg | M1 | Form & tabel generik, validasi, scope, API record internal |
| M3 | Relationship | 2 mg | M2 | record_links, EntitySelector, eager load, delete rules |
| M4 | Shared master data | 3 mg | M3 | Core entities, consumer registry, codelist, seed wilayah Kemendagri |
| M5 | Access hardening | 2 mg | M4 | UI role/assignment, clearance & masking, RLS, 2FA |
| M6 | Workflow | 3 mg | M5 | State machine, inbox tugas, history |
| M7 | Processing | 4 mg | M6 | DSL, compiler, formula parser, preview |
| M8 | Indicator | 2 mg | M7 | Definisi, snapshot, target, lineage |
| M9 | Presentation | 3 mg | M8 | Table/KPI/bar/line/pie/stacked, dashboard |
| **R1** | **Rilis MVP1** | 2 mg | M9 | Hardening, UAT, uji a11y manual, pentest internal |
| M10 | Event & notifikasi | 2 mg | M9 | Outbox relay, subscription, webhook, SLA workflow |
| M11 | Data product & API | 3 mg | M10 | Data product, OpenAPI, OAuth2 CC, cross-app query |
| M12 | Pentahelix | 3 mg | M11 | Registrasi mitra, trace, indikator kolaborasi |
| **R2** | **Rilis MVP2** | 2 mg | M12 | Uji arsitektur (doc 00 §23) |
| M13 | SPLP | 3 mg + waktu onboarding | M11 | Registrasi API, konektor inbound |
| M14 | GIS & statistik lanjutan | 4 mg | M9 | PostGIS view, peta choropleth (dengan alternatif tabel), Python worker |

Total berurutan sampai R2 adalah 41 minggu. Dengan paralelisasi (M10 dikerjakan bersamaan dengan R1) ditambah buffer 20% untuk kebutuhan yang berubah dan UAT, angka realistisnya ±10 bulan.

## 3. Acceptance criteria per milestone

### M0 — Fondasi
- [ ] Laravel 13 + Inertia v3 + React 19 + TS strict. `composer test`, `npm run typecheck`, `npm run lint`, dan `npm run test` hijau di CI.
- [ ] PHPStan level max lulus tanpa baseline pada kode baru.
- [ ] Extension `ltree`, `pg_trgm`, `pgcrypto`, `citext` aktif lewat migration.
- [ ] `PlatformBootstrap` seeder membuat root Pemda, app `core`, entity Core, dan admin pertama dalam satu transaksi.
- [ ] Login + 2FA (TOTP) untuk admin, rate limit login.
- [ ] CRUD organisasi dengan `path` ltree. Pindah parent memperbarui path turunan.
- [ ] `AuditLogger` dan `EventRecorder` (tabel outbox, belum ada relay) tersedia sebagai kontrak.
- [ ] Arch test: controller tidak memakai Eloquent modul lain, dan semua file memakai `declare(strict_types=1)`.
- [ ] Axe test untuk halaman login & organisasi = 0 pelanggaran.

### M1 — Metadata engine
- [ ] Admin membuat application, entity, dan field (semua tipe di doc 06 §1 kecuali `geo_point`) lewat UI form.
- [ ] Publish menghasilkan `entity_versions.compiled_schema`. Publish ulang tanpa perubahan ditolak.
- [ ] Validasi kode identifier + reserved words, dengan unit test injeksi (`year; DROP`, `data->>`, unicode homoglyph).
- [ ] Diff versi menampilkan kategori perubahan (doc 06 §4).
- [ ] Permission entity dibuat otomatis saat publish.

### M2 — Runtime CRUD
- [ ] Entity yang dipublikasikan langsung punya halaman index/create/show/edit **tanpa deploy**.
- [ ] Validasi server sesuai tipe. Payload berisi key tak dikenal → 422.
- [ ] Operator OPD A tidak bisa melihat/mengubah record OPD B (feature test IDOR per endpoint).
- [ ] Konflik edit (`lock_version`) menampilkan pesan dan opsi muat ulang.
- [ ] List 100.000 record fixture: p95 < 300 ms dengan filter field ter-index.
- [ ] Setiap create/update/delete tercatat di audit dengan diff.
- [ ] Form runtime berisi semua tipe field lolos axe dan uji keyboard.

### M3 — Relationship
- [ ] Relasi m2o & m2m, termasuk selector dengan pencarian async (debounce 300 ms, min 2 karakter).
- [ ] List dengan 3 kolom relasi = jumlah query konstan (tes menghitung query, tanpa N+1).
- [ ] Hapus target yang direferensikan → ditolak (`restrict`) atau dikosongkan (`nullify`) sesuai definisi.

### M4 — Shared master data
- [ ] Core.Organization/Region/Person/Employee/FiscalYear bisa dipilih sebagai target relasi dari app lain **hanya** setelah consumer disetujui.
- [ ] Seed wilayah dari Kepmendagri 300.2.2-2430/2025 (atau yang terbaru saat implementasi) dengan `source_ref`.
- [ ] Consumer tidak bisa mengubah master data (tes 403).
- [ ] NIK disimpan sebagai HMAC + terenkripsi. Tampilan NIK memerlukan clearance dan tercatat `pii.revealed`.

### M5 — Access hardening
- [ ] UI role & assignment dengan masa berlaku (Plt).
- [ ] Field `personal` ter-mask untuk role tanpa clearance, di UI, API, export, maupun event.
- [ ] RLS aktif di `objects`/`records`. Tes: query langsung tanpa `SET LOCAL` mengembalikan 0 baris private.
- [ ] Role aplikasi tidak bisa diberi permission platform (tes).

### M6 — Workflow
- [ ] Template verifikasi berjenjang bisa dipasang ke entity. Transisi hanya lewat `TransitionRecord`.
- [ ] Separation of duty dan komentar wajib ditegakkan.
- [ ] Inbox "Tugas saya" akurat menurut permission + scope.
- [ ] Record pada state terkunci tidak bisa diedit (UI & API).

### M7 — Processing
- [ ] DSL tervalidasi JSON Schema. Semua step di doc 07 §2.2 terkompilasi ke satu query.
- [ ] Parser formula: fuzz test 10.000 input acak tanpa crash atau SQL tak ter-binding.
- [ ] Hasil SqlEmitter = PhpEvaluator (property test).
- [ ] Preview 1 juta baris sumber < 10 dtk, dan timeout benar-benar menghentikan query.

### M8 — Indicator
- [ ] Indikator mereferensikan process versi tertentu. Snapshot per periode × dimensi.
- [ ] Perubahan formula → versi baru. Nilai versi lama tidak berubah (tes).
- [ ] Periode final tidak dihitung ulang, dan perubahan sumber memunculkan flag.
- [ ] "Asal angka ini" menampilkan run, DSL, dan watermark.

### M9 — Presentation
- [ ] View table/KPI/bar/line/pie/stacked + dashboard dengan parameter global (tahun, OPD).
- [ ] Dashboard hanya membaca `indicator_values`/data product (arch test: modul Presentation tidak memanggil Processing executor).
- [ ] Setiap chart punya ringkasan teks + tampilan tabel. Axe = 0 pelanggaran.
- [ ] Dashboard 12 widget: p95 render server < 500 ms.

### R1 — MVP1 "Monitoring Program" (lihat §4)

### M10 — Event & notifikasi
- [ ] Relay outbox dengan `SKIP LOCKED`. Kill -9 di tengah batch tidak menghilangkan event (tes).
- [ ] Listener idempotent (event ganda → efek sekali).
- [ ] Webhook ditandatangani HMAC dan terlindung SSRF (tes IP privat).
- [ ] SLA workflow + eskalasi.

### M11 — Data product & API
- [ ] Data product dengan kontrak lengkap (doc 09 §3.2). Data product publik ditolak jika mengandung field non-publik.
- [ ] OpenAPI 3.1 dipublikasikan, dan uji kontrak (schemathesis atau sejenisnya) hijau.
- [ ] OAuth2 client credentials + scope + rate limit.
- [ ] Small cell suppression berjalan.

### M12 — Pentahelix
- [ ] Registrasi & verifikasi mitra. Mitra hanya menulis contribution milik organisasinya.
- [ ] Trace Issue → Outcome (graf + tabel aksesibel).
- [ ] 5 indikator kolaborasi (doc 10 §5) dibuat **tanpa kode baru**.

### R2 — MVP2 + uji arsitektur
- [ ] Aplikasi "Research / Collaboration" dibangun **hanya dari metadata**.
- [ ] Checklist doc 00 §23: shared entity ✓, indikator existing ✓, data product ✓, master data ✓, event ✓, tanpa tabel master baru ✓, tanpa integrasi point-to-point ✓.
- [ ] Reuse ratio ≥ 60% (doc 01 §5).

### M13 — SPLP
- [ ] Minimal 1 data product terdaftar dan dikonsumsi lewat SPLP (lingkungan uji).
- [ ] Minimal 1 konektor inbound dari SPLP berjalan terjadwal dan idempotent.

### M14 — GIS & statistik
- [ ] Field `geo_point`/region geom, peta choropleth dengan alternatif tabel dan legenda teks.
- [ ] Python worker (FastAPI/queue) untuk statistik lanjut. Kontrak input/output lewat data product internal.

## 4. MVP1 — Monitoring Program (detail)

| Komponen | Isi |
|---|---|
| Shared (Core) | Organization, Region, FiscalYear |
| System entity (Collaboration) | Program, Activity |
| Entity app `monev` | `realization`: activity (m2o), period_month, physical_pct, budget_ceiling, budget_realized, notes, evidence (file) |
| Workflow | draft → submitted → verified → approved |
| Process | `monev.realization_by_program` (group program × bulan: sum anggaran, avg fisik terbobot) |
| Indikator | Capaian fisik program, Serapan anggaran OPD, Jumlah kegiatan terlambat |
| Dashboard | KPI serapan total, bar serapan per OPD, line tren bulanan, tabel kegiatan terlambat |
| Pengguna | Operator OPD, verifikator bidang, approver Bappeda, pimpinan (viewer) |

## 5. MVP2 — Research / Collaboration

Entity baru: `research` (m2o → organization academia, m2m → issue, m2m → dataset). Semua sisanya (Organization, Issue, Program, Dataset, indikator) **dipakai ulang**.

## 6. Definition of Done (semua milestone)

1. Acceptance criteria tercentang dan tes otomatisnya ada.
2. PHPStan max + TS strict + lint bersih.
3. Axe 0 pelanggaran di halaman yang disentuh.
4. Aksi penting tercatat di audit.
5. Dokumen di `docs/` diperbarui. Keputusan baru dicatat sebagai ADR.
6. Demo ke stakeholder terkait dan umpan balik dicatat.
