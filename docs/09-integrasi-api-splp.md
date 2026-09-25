# 09 — Integrasi, API & SPLP

## 1. Posisi terhadap SPLP

SPLP (Sistem Penghubung Layanan Pemerintah) adalah infrastruktur SPBE nasional yang dikelola Komdigi. Di level daerah ada SPL Pemda. Keduanya berfungsi sebagai perangkat integrasi untuk pertukaran layanan antar-instansi pusat dan daerah (Perpres 95/2018, lihat doc 14).

```text
   BARA (Pemda A)                     SPLP                        Pemda B / K/L
┌──────────────────┐      ┌─────────────────────────┐      ┌──────────────────┐
│ /api/v1 (OpenAPI)│◄────►│ API gateway / registry  │◄────►│ API masing-masing│
│ OAuth2 CC        │      │ (onboarding via Komdigi │      │                  │
│ Data products    │      │  + Diskominfo Provinsi) │      │                  │
└──────────────────┘      └─────────────────────────┘      └──────────────────┘
```

**Prinsip:** BARA tidak membuat integrasi point-to-point dengan Pemda lain. Semua pertukaran antar-instansi lewat SPLP. Integrasi internal Pemda memakai event dan API BARA sendiri.

### Kesiapan SPLP (dikerjakan di M11, onboarding di M13)

| Kebutuhan | Implementasi BARA | Status |
|---|---|---|
| Kontrak API terdokumentasi | OpenAPI 3.1 hasil generate, dipublikasikan per versi | Rencana |
| Autentikasi mesin | OAuth2 client credentials (Laravel Passport) + scope per data product | Rencana |
| Identitas global | UUIDv7 + URN `urn:bara:{kode_wilayah_pemda}:{entity}:{uuid}` | Rencana |
| Standar data | Kode referensi Satu Data (wilayah Kemendagri, urusan, dsb.) di codelist | Rencana |
| Rate limit & logging | Per api_client, `trace_id` diteruskan (`traceparent` W3C) | Rencana |
| Prosedur onboarding, format registrasi, mekanisme token gateway | **Perlu dikonfirmasi** ke Diskominfo Provinsi / Komdigi. Spesifikasi teknis gateway SPLP tidak dipublikasikan terbuka. | Terbuka |

## 2. Desain REST API

### 2.1 Konvensi

| Aspek | Aturan |
|---|---|
| Base | `/api/v1` (versi di path). Versi mayor baru hanya untuk perubahan tidak kompatibel. |
| Format | JSON, `snake_case`, tanggal ISO 8601, desimal sebagai string |
| Paginasi | Cursor: `?cursor=...&limit=100` (max 1.000); respons `meta.next_cursor` |
| Filter | `?filter[year]=2026&filter[opd]=<uuid>`, hanya field yang diizinkan |
| Sort | `?sort=-created_at` |
| Sparse fields | `?fields=title,year` |
| Error | RFC 9457 Problem Details (`application/problem+json`) |
| Idempotensi | Header `Idempotency-Key` untuk `POST` (disimpan 24 jam) |
| Concurrency | `ETag` / `If-Match` = `lock_version` |
| Deprecation | Header `Deprecation` dan `Sunset` (RFC 8594) |
| Rate limit | Header `RateLimit-*` standar IETF |

### 2.2 Endpoint

```text
# Data product (utama untuk konsumen eksternal & SPLP)
GET  /api/v1/data-products                          katalog (sesuai grant)
GET  /api/v1/data-products/{code}                   metadata + schema + versi
GET  /api/v1/data-products/{code}/data?version=1    data (paginasi, filter dimensi)

# Indikator
GET  /api/v1/indicators/{code}/values?period=2026&dimension[opd]=...

# Record generik (internal/aplikasi terpercaya)
GET    /api/v1/apps/{app}/entities/{entity}/records
POST   /api/v1/apps/{app}/entities/{entity}/records
GET    /api/v1/apps/{app}/entities/{entity}/records/{id}
PATCH  /api/v1/apps/{app}/entities/{entity}/records/{id}
POST   /api/v1/apps/{app}/entities/{entity}/records/{id}/actions/{action}

# Metadata (baca)
GET  /api/v1/apps/{app}/entities/{entity}/schema     JSON Schema versi published

# Core
GET  /api/v1/core/organizations?filter[sector]=academia
GET  /api/v1/core/regions?filter[parent]=64.72
```

### 2.3 Contoh respons data product

```json
{
  "data_product": "population_by_kelurahan",
  "version": "1.2.0",
  "quality_status": "verified",
  "as_of": "2026-09-01T00:00:00+08:00",
  "data": [
    { "region_code": "64.72.01.1001", "region_name": "…", "population": 12345, "period": "2026-S1" }
  ],
  "meta": { "next_cursor": "eyJ…", "limit": 100 },
  "links": { "schema": "/api/v1/data-products/population_by_kelurahan" }
}
```

## 3. Data product

### 3.1 Siklus hidup

```text
draft → (review data_steward) → published (provisional|verified) → deprecated (sunset_at) → retired
```

### 3.2 Kontrak minimum

| Atribut | Keterangan |
|---|---|
| `owner` | Organization penanggung jawab |
| `schema` | JSON Schema output. Perubahan tidak kompatibel = versi mayor baru |
| `description` | Definisi operasional, cakupan, batasan |
| `version` | Semver |
| `update_frequency` | `daily`, `monthly`, `on_event` |
| `source` | Indicator / process / entity (lineage otomatis) |
| `access_policy` | `public` / `internal` / `restricted` + grants |
| `quality_status` | `draft`, `provisional`, `verified`, `deprecated` |
| `as_of` | Watermark data |

### 3.3 Aturan

- Data product `public` **tidak boleh** berisi field dengan klasifikasi di atas `public`. Ini dicek saat publish berdasarkan lineage kolom.
- Agregat dengan jumlah kecil (misalnya < 5 orang per sel) disembunyikan atau digabung (*small cell suppression*) untuk mencegah re-identifikasi.
- Data product yang dikonsumsi SPLP wajib `verified`.

## 4. Integrasi masuk (inbound)

Sumber eksternal (misalnya data dari aplikasi nasional lewat SPLP) diperlakukan sebagai **source connector**:

```text
Connector (config: endpoint SPLP, auth, mapping) → staging (JSONB mentah + hash)
  → validasi & mapping ke entity → upsert record (owner = organisasi penyedia) → event
```

Connector dijalankan sebagai job terjadwal, idempotent berbasis hash baris, dan setiap run dicatat seperti `process_runs`.

## 5. Keamanan API

- Token pengguna: Sanctum (SPA/mobile first-party). Token mesin: OAuth2 client credentials, masa berlaku 1 jam.
- Scope: `data_product:{code}:read`, `records:{app}.{entity}:read|write`, `indicator:{code}:read`.
- Semua akses API mesin dicatat (tanpa isi payload personal). Anomali volume memicu alert.
- CORS: default tertutup, allowlist per client.
