# 05 — Keamanan & Akses

## 1. Model otorisasi (4 lapis)

Sebuah aksi **diizinkan** hanya jika **semua** lapis berikut lolos:

```text
1. Permission   : user punya role yang memuat permission untuk aksi ini?
2. Scope        : role itu di-assign pada organisasi yang mencakup owner objek?
3. Visibility   : kalau lapis 2 gagal, apakah visibility objek membuka akses baca untuk user ini?
4. Clearance    : field dengan classification di atas clearance role → disembunyikan/di-mask
   + Workflow   : state saat ini mengizinkan edit (locks_record = false)?
   + Ownership  : aksi tulis pada shared entity hanya untuk owner app (consumer hanya reference/read)
```

### 1.1 Evaluasi scope dengan `ltree`

User A punya assignment `operator` di `pemda.dinkes` dengan `include_descendants = true`.
Record milik `pemda.dinkes.bid_p2p` dengan `owner_path = 'pemda.dinkes.bid_p2p'`.

```sql
-- Filter daftar record yang boleh dibaca (dibangun sekali per request, dipakai di semua query)
WHERE o.owner_path <@ ANY(:scope_paths)          -- path assignment dengan include_descendants
   OR o.owner_path =  ANY(:exact_paths)          -- assignment tanpa descendants
   OR o.visibility = 'internal' AND :is_internal
   OR o.visibility = 'partner'  AND (:is_internal OR :is_verified_partner)
   OR o.visibility = 'public'
```

`scope_paths` dihitung dari `role_assignments` yang aktif (`now()` di antara `valid_from`/`valid_to`) dan yang role-nya memuat `{app}.{entity}.view`. Hasilnya di-cache per user selama 5 menit, dan cache dibuang saat event `role.assigned` atau `role.revoked`.

### 1.2 Implementasi di Laravel

| Komponen | Tugas |
|---|---|
| `AccessContext` (readonly DTO per request) | user, kind, daftar `(permission → paths[])`, clearance tertinggi per app |
| `ScopedRecordQuery` | Menambahkan klausa scope ke setiap query `records`/`objects`. **Tidak ada jalan pintas tanpa scope** kecuali `SystemContext` eksplisit untuk job internal. |
| `RecordPolicy` | `view/update/delete` per objek: permission + scope + workflow lock |
| `FieldGate` | Memfilter field sebelum dikirim ke Inertia/API berdasarkan clearance. Masking: `personal` → `3201********0001` (4 awal + 4 akhir) |
| `Gate::before` | Super admin platform **tidak** otomatis membaca data `personal_specific`. Akses tetap dicatat. |

### 1.3 Row-Level Security (lapis pertahanan kedua, M5)

```sql
ALTER TABLE objects ENABLE ROW LEVEL SECURITY;
CREATE POLICY objects_scope ON objects
  USING (
    current_setting('bara.bypass', true) = 'on'
    OR owner_path <@ ANY (string_to_array(current_setting('bara.scope_paths', true), ',')::ltree[])
    OR visibility = 'public'
    OR (visibility IN ('internal','partner') AND current_setting('bara.is_internal', true) = 'on')
  );
```

- Nilai di-set dengan `SET LOCAL` di awal setiap transaksi oleh middleware. `SET LOCAL` aman dipakai bersama PgBouncer mode transaction.
- Aplikasi terhubung sebagai role `bara_app`, **bukan** owner tabel. Owner tabel (`bara_owner`) hanya dipakai migration.
- RLS adalah pengaman terakhir kalau ada bug di `ScopedRecordQuery`. Filter aplikasi tetap wajib, karena lebih cepat dan pesan errornya lebih jelas.

## 2. Role bawaan

| Role | Level | Clearance | Isi |
|---|---|---|---|
| `platform_admin` | platform | `restricted` | Kelola aplikasi, organisasi, role platform, codelist. Tidak otomatis membaca data personal. |
| `data_steward` (Walidata) | platform | `internal` | Setujui consumer shared entity, publikasi data product, kualitas data |
| `dpo` | platform | `personal_specific` | Akses audit PII, DPIA, respons permintaan subjek data |
| `app_admin` | aplikasi | `internal` | Kelola metadata app (draft), form, view, workflow app tsb. Publish butuh `platform.metadata.publish`. |
| `operator` | aplikasi | `internal` | CRUD record dalam scope |
| `verifikator` | aplikasi | `internal` | Transisi verify/return/reject |
| `approver` | aplikasi | `internal` | Transisi approve/publish |
| `viewer` | aplikasi | `internal` | Baca record & dashboard |
| `partner_contributor` | aplikasi | `public` | User eksternal: kelola contribution/evidence milik organisasinya |
| `auditor` | platform | `restricted` | Baca audit log, read-only semua metadata |

Role dan clearance yang lebih tinggi (`personal`) diberikan per aplikasi secara eksplisit dengan alasan tercatat.

## 3. Pelindungan data pribadi (UU 27/2022 & PP 33/2026)

| Kewajiban | Implementasi di platform | Dasar |
|---|---|---|
| Klasifikasi data pribadi umum vs spesifik | `fields.classification` (`personal` / `personal_specific`); wajib diisi saat membuat field. Wizard memberi peringatan jika label mengandung "NIK", "kesehatan", "agama", "anak", dst. | UU 27/2022 Pasal 4 |
| Dasar pemrosesan & tujuan | `entities.config.processing_basis` + `purpose` wajib jika ada field personal. Ditampilkan di form sebagai pemberitahuan. | UU 27/2022 Pasal 20 |
| Minimisasi | Field personal di-review oleh `dpo` sebelum entity dipublikasikan (gate di `PublishEntityVersion`) | UU 27/2022 Pasal 16 ayat (2) |
| Penilaian dampak (DPIA) | Checklist DPIA terlampir pada entity dengan field `personal_specific` atau pemrosesan skala besar | UU 27/2022 Pasal 34 |
| Pejabat PDP | Role `dpo` | UU 27/2022 Pasal 53 |
| Notifikasi kegagalan PDP | Runbook insiden (doc 13) dengan tenggat notifikasi | UU 27/2022 Pasal 46 |
| Hak subjek data (akses, koreksi, hapus) | Fitur "Data Subject Request": cari semua objek yang terkait `core_persons.id` lewat `record_links`, lalu export/koreksi/anonimisasi | UU 27/2022 Pasal 5–13 |
| Retensi | `entities.config.retention_months`; job anonimisasi bulanan | UU 27/2022 Pasal 16 ayat (2) |
| Aturan teknis pelaksanaan | PP 33/2026 berlaku **16 Januari 2027**. Detail kewajiban teknis pengendali perlu dipetakan ulang setelah teks final dibaca lengkap. | PP 33/2026 |

> Nomor pasal di atas perlu diverifikasi terhadap teks resmi (JDIH BPK) sebelum dokumen ini dipakai sebagai rujukan formal. Tabel ini adalah pemetaan teknis, **bukan** nasihat hukum.

## 4. Threat model (STRIDE, fokus komponen berisiko)

| Ancaman | Contoh di platform | Mitigasi |
|---|---|---|
| **S**poofing | Credential stuffing; token API bocor | Rate limit login (5/menit/IP+email), 2FA wajib untuk admin & verifikator, token API ber-scope + rotasi 90 hari, OAuth2 client credentials untuk mesin |
| **T**ampering | Edit record di state terkunci; mengubah `owner_org_id` lewat payload | `owner_org_id` tidak pernah diambil dari input tanpa cek scope; `lock_version` untuk konflik; workflow lock di Policy |
| **R**epudiation | Verifikator menyangkal approve | `workflow_history` + `audit_logs` append-only, `trace_id`, hash chain opsional |
| **I**nformation disclosure | IDOR `/records/{uuid}`; data personal ikut di payload Inertia; event payload berisi PII | Policy per objek, `FieldGate` sebelum serialisasi, payload event hanya id, Inertia props di-whitelist per field |
| **D**enial of service | Process dengan join besar; export jutaan baris; regex jahat di `pattern` | `statement_timeout` per run (default 30 dtk, max 5 mnt), batas baris output, export via job + chunk, validasi regex dengan batas panjang dan uji *catastrophic backtracking* sebelum dipublikasikan |
| **E**levation of privilege | `app_admin` membuat role dengan permission platform; formula injeksi SQL | Role aplikasi hanya boleh memuat permission `{app}.*` miliknya; formula dikompilasi dari AST whitelist (doc 07); identifier dari metadata |

### Kontrol spesifik metadata-driven

1. **Identifier injection.** Kode entity/field divalidasi dengan regex `^[a-z][a-z0-9_]{1,62}$` dan tidak boleh berupa kata kunci SQL reserved. Di query, field JSONB diakses sebagai `data->>?` dengan binding. Nama kolom fisik diambil dari `physical_table` yang sudah di-whitelist lewat migration.
2. **Stored XSS lewat label/help_text.** Semua metadata dirender React sebagai text (auto-escape). Field `rich_text` disanitasi server-side dengan allowlist tag (mis. `symfony/html-sanitizer`), dan `dangerouslySetInnerHTML` hanya dipakai untuk output sanitizer.
3. **Mass assignment.** Payload record difilter ke field yang ada di `compiled_schema` versi aktif. Key lain ditolak (422), bukan diabaikan diam-diam.
4. **File upload.** MIME dideteksi server, ada allowlist ekstensi per field, batas ukuran, nama file acak, disajikan lewat signed URL dengan `Content-Disposition: attachment`, dan dipindai ClamAV sebelum `scan_status = clean`.
5. **SSRF pada webhook.** URL webhook harus HTTPS, IP privat/loopback/link-local diblokir setelah resolusi DNS, domain di-allowlist oleh `platform_admin`, dan payload ditandatangani HMAC-SHA256.

## 5. Aksi yang wajib diaudit

`auth.login`, `auth.login_failed`, `auth.2fa_changed`, `record.create|update|delete|restore`,
`record.export`, `workflow.transition`, `metadata.publish`, `metadata.archive`,
`role.create|update`, `role.assigned|revoked`, `api_client.create|rotate|revoke`,
`data_product.publish|grant|revoke`, `pii.revealed`, `dsr.fulfilled`, `settings.update`.

## 6. Baseline keamanan aplikasi

- Header: CSP ketat (nonce untuk Inertia/Vite), HSTS, `X-Content-Type-Options`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`.
- Session: cookie `Secure`, `HttpOnly`, `SameSite=Lax`, idle timeout 30 menit untuk admin.
- Password: minimal 12 karakter, dicek terhadap daftar kebocoran (`Password::uncompromised()`), hashing Argon2id.
- Dependency: `composer audit` + `npm audit` di CI, Dependabot/Renovate.
- Secret: tidak ada di repo. `.env` dari secret store server, dan `APP_KEY`, pepper NIK, serta kunci enkripsi dirotasi dengan prosedur terdokumentasi.
- Standar acuan untuk audit keamanan SPBE: pedoman BSSN tentang manajemen keamanan informasi SPBE (lihat doc 14 untuk status verifikasi nomor peraturannya).
