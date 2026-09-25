# 15 — Risk Register

Skala: Kemungkinan (K) dan Dampak (D) 1–5. Skor = K × D. Skor ≥ 12 dipantau di setiap milestone.

| # | Risiko | K | D | Skor | Mitigasi | Pemilik |
|---|---|---|---|---|---|---|
| R01 | **Inner-platform effect.** Metadata engine tumbuh menjadi "database dalam database" yang lambat dan rumit. | 4 | 5 | 20 | Hybrid storage + jalur promosi ke tabel fisik (ADR 0004); daftar non-goals ketat; setiap tipe/step baru harus lewat ADR | Tech lead |
| R02 | Performa JSONB turun pada entity besar | 3 | 4 | 12 | Index ekspresi terkontrol, batas 8 index, uji beban tiap milestone, promosi fisik | Tech lead |
| R03 | Scope creep ke visual builder sebelum engine stabil | 4 | 3 | 12 | Visual builder baru boleh dibahas setelah R2 | Product owner |
| R04 | Kebocoran data pribadi lewat processing/export/event | 2 | 5 | 10 | FieldGate di semua keluaran, payload event tanpa PII, small cell suppression, audit `pii.revealed` | DPO + tech lead |
| R05 | OPD tidak mau memindahkan master data (ownership politis) | 4 | 4 | 16 | Tetapkan owner lewat Perkada/SK; mulai dari master yang sudah jelas pemiliknya (wilayah, OPD); tunjukkan manfaat via MVP1 | Diskominfo |
| R06 | Onboarding SPLP lambat / spesifikasi tidak tersedia | 4 | 3 | 12 | Bangun kontrak API standar (OpenAPI, OAuth2) sejak M11 agar adaptasi ke gateway kecil; mulai komunikasi sejak M9 | Diskominfo |
| R07 | Formula/process menghasilkan query berat (DoS internal) | 3 | 3 | 9 | EXPLAIN guard, `statement_timeout`, queue terpisah, batas output | Tech lead |
| R08 | Evolusi skema merusak indikator historis | 3 | 4 | 12 | Versioning entity/process/indicator, dependency check, periode final | Tech lead |
| R09 | Tim kecil & ketergantungan pada satu orang (bus factor) | 4 | 4 | 16 | ADR + dokumen ini, pair review wajib, CLAUDE.md untuk konsistensi agen AI, modul dengan pemilik ganda | PM |
| R10 | Aksesibilitas turun karena UI digenerate dari metadata | 3 | 3 | 9 | Validator publish a11y (doc 11 §3.2), axe di CI dengan fixture semua tipe field | Frontend lead |
| R11 | Regulasi berubah (PP 33/2026 berlaku 2027, revisi SPBE) | 3 | 3 | 9 | Doc 14 ditinjau tiap kuartal; kontrol privasi dibuat konfiguratif | DPO |
| R12 | Kualitas data sumber rendah → indikator tidak dipercaya | 4 | 4 | 16 | Validasi di input, workflow verifikasi, `quality_status`, lineage "asal angka ini" | Data steward |
| R13 | Mitra Pentahelix pasif / tidak mengisi | 3 | 3 | 9 | Onboarding ringan, form sederhana di mobile, kontribusi minimal = 1 evidence | Bappeda |
| R14 | Infrastruktur on-prem tidak siap (backup, standby) | 3 | 5 | 15 | Checklist infra di M0; uji restore sebelum R1 sebagai syarat go-live | Diskominfo |
