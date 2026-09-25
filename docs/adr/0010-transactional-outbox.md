# ADR 0010 — Transactional Outbox untuk Domain Event

- **Status:** Diterima
- **Tanggal:** 2026-09-25
- **Terkait:** doc 08 §2

## Konteks
`event()` + queue Redis biasa bisa kehilangan event (Redis gagal setelah commit) atau menghasilkan event hantu (dispatch sebelum rollback).

## Keputusan
- Event ditulis ke `outbox_events` dalam transaksi yang sama dengan perubahan data.
- Relay (`FOR UPDATE SKIP LOCKED`, batch 100, tiap 1 detik) men-dispatch ke queue dan menandai `published_at`.
- Semantik at-least-once. Listener idempotent via `processed_events`.
- Payload tanpa PII, berversi (`"v": 1`).

## Alternatif
| Opsi | Kekurangan |
|---|---|
| `DB::afterCommit` + dispatch | Tetap bisa hilang jika proses mati di antara commit dan dispatch |
| Kafka/RabbitMQ + Debezium CDC | Terlalu berat untuk skala saat ini |

## Konsekuensi
- (+) Tidak ada event yang hilang, dan event bisa di-replay.
- (−) Latensi tambahan ≤ 1–2 detik. Ada satu proses relay yang harus dimonitor (alert outbox lag).
