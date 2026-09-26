# ADR 0015 — Clearance Data Terikat Role Aplikasi dan Unit yang Di-grant

- **Status:** Diterima
- **Tanggal:** 2026-09-27
- **Terkait:** ADR 0007, ADR 0013, docs/05 §1.2, audit M0–M2 (SEC-001, SEC-002, SEC-003, SEC-004)

## Konteks

Di M2, clearance dihitung sebagai clearance tertinggi dari **semua** role aktif user, termasuk
role platform (`dpo` ber-clearance `personal_specific`), tanpa melihat unit. Akibatnya:

1. User `dpo` yang juga `viewer` di satu OPD melihat data pribadi spesifik tanpa samaran di
   **semua** record yang terlihat olehnya, termasuk record OPD lain yang terbuka lewat
   visibilitas `internal`.
2. Petugas ber-clearance tinggi di OPD A melihat field sensitif record OPD B hanya karena
   record itu ber-visibilitas internal.
3. Judul record (`records.title`) dan judul target relasi keluar tanpa `FieldGate`, sehingga
   nilai field `restricted` bisa bocor lewat daftar, relasi, dan audit.

Visibilitas (lapis 3 di docs/05 §1) dimaksudkan membuka **keberadaan dan data umum** record
lintas unit, bukan menaikkan hak baca field sensitif.

## Keputusan

1. **Clearance runtime hanya dari role aplikasi** entity tersebut, pada unit yang masih aktif.
   Role platform (`platform_admin`, `auditor`, `dpo`) tidak memberi clearance data runtime.
   Pejabat PDP meninjau metadata, bukan membaca data.
2. **Clearance hanya berlaku di dalam grant.** Untuk record yang terbaca lewat visibilitas saja
   (di luar unit yang di-grant permission `view`), `FieldGate` dibatasi paling tinggi `internal`
   (`FieldGate::capped`). Berlaku di daftar, detail, form ubah, dan unduh berkas.
3. **Filter pada field di atas internal** hanya dijalankan di dalam unit yang di-grant
   (`ScopeFactory::granted`), supaya pencocokan nilai tidak menjadi oracle keberadaan data unit lain.
4. **Judul record hanya dari field publik/internal.** `DraftValidator` menolak placeholder field
   di atas internal; `TitleRenderer` juga mengecualikannya (defense in depth).
5. **Relasi:** judul target diselesaikan dengan cakupan baca **pembaca** pada entity target;
   target di luar cakupan diberi label `(di luar kewenangan Anda)`. Opsi dan validasi relasi
   mensyaratkan permission `view` pada entity target (lapis 1) sebelum visibilitas berlaku.

## Alternatif

| Opsi                                                          | Kekurangan                                                                                                                      |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Clearance per record dari role yang grant-nya mencakup record | Lebih presisi untuk user dengan beberapa role di unit berbeda, tetapi butuh query per baris; ditunda sampai ada kebutuhan nyata |
| Tetap global, andalkan visibilitas `restricted`               | Admin entity harus selalu ingat; kebocoran terjadi diam-diam                                                                    |

## Konsekuensi

- User dengan dua role di unit berbeda (mis. operator di A dengan clearance `personal`, viewer
  di B dengan clearance internal) memakai clearance tertinggi di **semua** unit yang di-grant.
  Diterima untuk sekarang; bila perlu, pindah ke clearance per record (alternatif pertama).
- Operator tidak bisa memfilter field `restricted` milik unit lain. Ini disengaja.
- Pejabat PDP yang butuh membaca data pribadi harus diberi role aplikasi eksplisit
  (docs/05 §2), sehingga aksesnya tercatat per aplikasi.
