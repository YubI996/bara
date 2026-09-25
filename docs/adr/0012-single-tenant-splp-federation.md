# ADR 0012 — Single-Tenant; Multi-Pemda lewat Federasi SPLP

- **Status:** Diterima
- **Tanggal:** 2026-09-25
- **Terkait:** doc 09

## Konteks

Platform untuk satu Pemda. Di masa depan perlu bertukar data dengan Pemda lain melalui SPLP (infrastruktur SPBE nasional).

## Keputusan

- **Tidak ada `tenant_id`.** Setiap Pemda menjalankan instance sendiri (data, owner, dan hosting tetap di Pemda masing-masing).
- Pertukaran antar-Pemda/K/L hanya lewat **data product + API** yang didaftarkan ke SPLP. Tidak ada database bersama atau replikasi langsung.
- Keunikan global dijamin UUIDv7 + URN berprefiks kode wilayah Kemendagri Pemda.
- Instance kode wilayah Pemda disimpan di konfigurasi (`BARA_REGION_CODE`).

## Alternatif

| Opsi                               | Kekurangan                                                                                               |
| ---------------------------------- | -------------------------------------------------------------------------------------------------------- |
| Multi-tenant satu DB (`tenant_id`) | Risiko kebocoran antar-Pemda, isu kedaulatan data, kompleksitas di setiap query; tidak sesuai model SPLP |
| Schema-per-tenant                  | Operasional migration berat                                                                              |

## Konsekuensi

- (+) Model sederhana, dan batas data sesuai batas kewenangan.
- (+) Kalau platform ini diadopsi Pemda lain, cukup deploy instance baru. Kode sama, konfigurasi berbeda.
- (−) Agregasi lintas Pemda dilakukan konsumen (provinsi/pusat) melalui data product, bukan query langsung.
