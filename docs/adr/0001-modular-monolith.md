# ADR 0001 — Modular Monolith di Laravel 13

- **Status:** Diterima
- **Tanggal:** 2026-09-25

## Konteks
Tim 2–4 developer, satu Pemda, domain masih berkembang. Rencana awal sudah menolak microservices yang terlalu dini.

## Keputusan
- Satu codebase Laravel 13 (PHP 8.5, `declare(strict_types=1)`), satu deployment, satu cluster PostgreSQL.
- Modul di `app/Modules/{Nama}` dengan kontrak publik di `Contracts/`. Modul tidak boleh memakai Eloquent model milik modul lain.
- Use case ditulis sebagai **Action** (satu kelas, satu metode `execute`), sedangkan controller tetap tipis.
- Aturan dependensi (doc 03 §3) ditegakkan dengan `pest-plugin-arch`.

## Alternatif
| Opsi | Kelebihan | Kekurangan |
|---|---|---|
| Microservices | Skala independen | Biaya operasional, konsistensi terdistribusi, tim terlalu kecil |
| Monolith tanpa batas modul | Cepat di awal | Jadi *big ball of mud*; sulit diekstrak nanti |
| Next.js full-stack + Prisma | Satu bahasa (TS) | Queue/job/scheduler/policy harus dirakit sendiri; ekosistem Laravel lebih matang untuk domain CRUD + workflow + job |

## Konsekuensi
- (+) Transaksi ACID lintas modul (data + audit + outbox dalam satu commit).
- (+) Modul bisa diekstrak nanti karena batasnya sudah jelas.
- (−) Disiplin batas modul harus dijaga lewat arch test, bukan hanya konvensi.
