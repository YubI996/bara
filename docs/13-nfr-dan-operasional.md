# 13 — Kebutuhan Non-Fungsional & Operasional

## 1. Target kualitas

| Atribut                | Target                                                      | Cara ukur                                          |
| ---------------------- | ----------------------------------------------------------- | -------------------------------------------------- |
| Latensi CRUD           | p95 < 300 ms (server), p99 < 1 dtk                          | APM / log `duration_ms`                            |
| Latensi dashboard      | p95 < 500 ms untuk 12 widget                                | Membaca snapshot, bukan hitung langsung            |
| Throughput             | 50 req/dtk berkelanjutan, 200 req/dtk puncak                | Uji beban k6 sebelum R1                            |
| Ketersediaan           | 99,5% bulanan (jam kerja 07.00–22.00 prioritas)             | Uptime monitor eksternal                           |
| RPO                    | ≤ 15 menit                                                  | WAL archiving                                      |
| RTO                    | ≤ 4 jam                                                     | Uji restore per kuartal                            |
| Kesegaran indikator    | ≤ 10 menit setelah approval                                 | Selisih `computed_at` terhadap `occurred_at` event |
| Aksesibilitas          | WCAG 2.2 AA                                                 | doc 11                                             |
| Kompatibilitas browser | 2 versi terakhir Chrome/Edge/Firefox/Safari, Android Chrome | Playwright matrix                                  |

## 2. Topologi deployment

Hosting di pusat data Pemda atau PDN. Data penyelenggara sistem elektronik lingkup publik wajib dikelola, diproses, dan disimpan di Indonesia (PP 71/2019, lihat doc 14).

```text
                 ┌──────────── DMZ ────────────┐
Internet ──► WAF / reverse proxy (Nginx) ── TLS 1.2+ (1.3 diutamakan)
                 └─────────────┬───────────────┘
                               │
         ┌─────────────────────┼─────────────────────┐
         │ app-1 (php-fpm/Octane) │ app-2             │   ← stateless, ≥ 2 node
         │ worker-1 (Horizon)     │ scheduler (1 aktif, lock) │
         └─────────────────────┬─────────────────────┘
                               │ jaringan internal
         ┌─────────────┬───────┴───────┬──────────────┐
         │ PostgreSQL 18 primary │ standby (streaming) │ PgBouncer (transaction mode)
         │ Redis (+ replica)     │ Object storage       │ ClamAV
         └─────────────┴───────────────┴──────────────┘
```

Ukuran awal (asumsi doc 01 §6): app 2 × (4 vCPU, 8 GB), worker 1 × (4 vCPU, 8 GB), DB 1 × (8 vCPU, 32 GB, SSD NVMe 500 GB) + standby setara, Redis 2 GB.

## 3. Konfigurasi PostgreSQL penting

| Setting                               | Nilai awal                                   | Alasan                                                      |
| ------------------------------------- | -------------------------------------------- | ----------------------------------------------------------- |
| `shared_buffers`                      | 25% RAM                                      | Standar                                                     |
| `effective_cache_size`                | 70% RAM                                      | Estimasi planner                                            |
| `work_mem`                            | 32 MB (naikkan per session untuk processing) | Agregasi & sort                                             |
| `statement_timeout`                   | 30 s (role `bara_app`), di-override per run  | Cegah query liar                                            |
| `idle_in_transaction_session_timeout` | 60 s                                         | Cegah lock menggantung                                      |
| `wal_level`                           | `replica`                                    | Standby + PITR                                              |
| `jsonb`                               | –                                            | Pantau ukuran baris; TOAST di atas ±2 KB memperlambat akses |

## 4. Backup & pemulihan

- **pgBackRest**: full mingguan, differential harian, WAL archiving kontinu → PITR (RPO ≤ 15 menit).
- Salinan backup **terenkripsi** di lokasi fisik berbeda (DRC Pemda atau fasilitas pemerintah lain).
- Object storage: versioning + replikasi harian.
- **Uji restore setiap kuartal** ke lingkungan staging, dicatat waktu dan hasilnya. Backup yang tidak pernah diuji restore dianggap belum ada.

## 5. Observability

| Sinyal  | Implementasi                                                                                                     |
| ------- | ---------------------------------------------------------------------------------------------------------------- |
| Log     | JSON terstruktur (`trace_id`, `user_id`, `route`, `duration_ms`), tanpa PII, ke Loki/ELK                         |
| Metrik  | Prometheus: request rate/latency, queue depth, outbox lag, `process_runs` durasi, koneksi DB                     |
| Trace   | OpenTelemetry (propagasi `traceparent` ke job & webhook)                                                         |
| Error   | Sentry self-hosted / GlitchTip (on-prem)                                                                         |
| Alert   | Outbox lag > 5 mnt; queue `processing` > 100; error rate > 2%; disk DB > 75%; backup gagal; login gagal melonjak |
| Laravel | Pulse (ringkasan internal), Horizon (queue)                                                                      |

## 6. Lingkungan & rilis

| Lingkungan | Data                                | Tujuan                      |
| ---------- | ----------------------------------- | --------------------------- |
| local      | fixture sintetis                    | Pengembangan                |
| ci         | ephemeral                           | Tes otomatis                |
| staging    | salinan produksi **teranonimisasi** | UAT, uji restore, uji beban |
| production | riil                                | –                           |

- Branch trunk-based + PR wajib review. Rilis ditandai tag semver.
- Migration harus **backward compatible** satu rilis (expand → migrate → contract), supaya rolling deploy tanpa downtime bisa dilakukan.
- `CREATE INDEX CONCURRENTLY` untuk tabel besar dijalankan di luar transaksi migration.

## 7. Runbook insiden (ringkas)

1. **Deteksi** → alert/laporan → buka tiket insiden, tetapkan komandan insiden.
2. **Kendalikan** → cabut token/API client yang disalahgunakan, blokir IP di WAF, aktifkan mode maintenance bila perlu.
3. **Nilai dampak** → dari audit log: data apa, berapa subjek, sejak kapan.
4. **Notifikasi** → jika melibatkan data pribadi: DPO menyiapkan pemberitahuan ke subjek data dan lembaga sesuai UU 27/2022 Pasal 46 (tenggat 3 × 24 jam, verifikasi terhadap teks resmi dan PP 33/2026). Koordinasi dengan CSIRT daerah/BSSN.
5. **Pulihkan** → patch, rotasi secret, restore bila perlu.
6. **Pasca-insiden** → laporan tanpa menyalahkan individu (blameless), tindak lanjut dicatat sebagai tiket.
