# ADR 0007 — Otorisasi: RBAC + Scope Organisasi + Clearance + RLS

- **Status:** Diterima
- **Tanggal:** 2026-09-25
- **Terkait:** doc 05

## Konteks
Hierarki Pemda (Pemda → OPD → bidang → seksi/UPTD) menuntut akses berbasis posisi organisasi, bukan hanya role. Ada juga data pribadi (UU 27/2022) dan mitra eksternal.

## Keputusan
- `role_assignments(user, role, scope_org_id, include_descendants, valid_from/to)`.
- Hierarki organisasi disimpan dengan `ltree`. Evaluasi scope memakai `owner_path <@ scope_path`.
- `fields.classification` × `roles.clearance` untuk masking/penyembunyian field di semua keluaran.
- Filter scope di aplikasi (`ScopedRecordQuery`) wajib. **RLS PostgreSQL** menjadi lapis kedua (M5), dengan `SET LOCAL` per transaksi.
- Tidak memakai `spatie/laravel-permission` untuk scope. Paket itu tidak memodelkan scope hierarkis. Tabel role/permission dibangun sendiri (sederhana), sehingga cache & evaluasi tetap terkendali.

## Alternatif
| Opsi | Kekurangan |
|---|---|
| RBAC murni | Tidak bisa membatasi OPD A vs OPD B |
| spatie/permission + teams | "Team" datar, tanpa pewarisan hierarki |
| ReBAC eksternal (OpenFGA/SpiceDB) | Komponen tambahan; belum perlu pada skala ini (bisa dipertimbangkan ulang setelah R2) |

## Konsekuensi
- (+) Kebijakan akses bisa dijelaskan ke auditor: siapa, di unit mana, sampai level data apa.
- (−) Semua query data harus lewat repository ber-scope, ditegakkan arch test + RLS.
