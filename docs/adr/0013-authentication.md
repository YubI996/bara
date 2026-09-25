# ADR 0013 — Autentikasi: Lokal + Siap OIDC, Mesin via OAuth2 Client Credentials

- **Status:** Diterima
- **Tanggal:** 2026-09-25

## Konteks

Pengguna ASN, mitra eksternal, dan mesin (aplikasi lain, SPLP). Pemda mungkin memiliki atau akan memiliki SSO.

## Keputusan

- Login lokal (Laravel Fortify): Argon2id, `Password::uncompromised()`, TOTP 2FA wajib untuk role admin/verifikator/approver/dpo.
- Abstraksi `IdentityProvider` sehingga OIDC (mis. Keycloak Pemda atau SSO nasional bila tersedia) bisa ditambahkan tanpa mengubah model user. Pencocokan akun via email terverifikasi + NIP.
- API first-party: Sanctum. API mesin: OAuth2 client credentials (Laravel Passport), token 1 jam, scope granular.
- Mitra eksternal: self-register + verifikasi email + verifikasi organisasi oleh admin.

## Konsekuensi

- (+) Bisa go-live tanpa menunggu SSO, dan migrasi ke SSO nanti tidak disruptif.
- (−) Dua paket token (Sanctum + Passport), dengan batas penggunaan yang jelas.
