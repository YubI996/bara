# 07 — Processing Engine & Indicator Layer

## 1. Prinsip

- Process adalah **data** (DSL JSON berversi), bukan kode. Process dikompilasi menjadi **satu query SQL** (rangkaian CTE) memakai Laravel Query Builder dengan binding.
- PostgreSQL yang melakukan kerja berat (filter, join, agregasi, statistik), bukan loop PHP.
- Output process tidak pernah menimpa data sumber. Output hanya dibaca langsung (preview), disimpan sebagai snapshot indikator, atau disajikan sebagai data product.

## 2. DSL

### 2.1 Contoh: tingkat publikasi dataset per OPD

```json
{
  "source": { "entity": "satudata.dataset", "alias": "d" },
  "parameters": [ { "name": "year", "type": "integer", "required": true } ],
  "steps": [
    { "op": "filter", "where": { "all": [
        { "field": "d.year", "cmp": "=", "value": { "param": "year" } }
    ]}},
    { "op": "join", "relationship": "d.producer_org", "alias": "opd", "kind": "inner" },
    { "op": "group", "by": [ { "expr": "opd.id", "as": "opd" } ] },
    { "op": "aggregate", "measures": [
        { "as": "registered", "fn": "count" },
        { "as": "published",  "fn": "count",
          "where": { "field": "d._state", "cmp": "=", "value": "published" } }
    ]},
    { "op": "formula", "as": "publication_rate",
      "expr": "round(safe_div(published, registered) * 100, 2)" },
    { "op": "classify", "field": "publication_rate", "as": "category", "bins": [
        { "lt": 50, "label": "rendah" },
        { "lt": 80, "label": "sedang" },
        { "label": "tinggi" }
    ]},
    { "op": "sort", "by": [ { "field": "publication_rate", "dir": "desc" } ] }
  ],
  "output": [ "opd", "registered", "published", "publication_rate", "category" ]
}
```

### 2.2 Step yang didukung

| Step | Fungsi | Hasil SQL |
|---|---|---|
| `filter` | kondisi `all`/`any`/`not`, cmp `= != < <= > >= in not_in between is_null like` | `WHERE` (atau `HAVING` jika setelah `aggregate`) |
| `select` | pilih/ubah nama kolom, ekspresi per baris | `SELECT expr AS alias` |
| `join` | ikuti **relationship yang terdefinisi** (bukan kolom bebas) | `JOIN record_links + records/core` |
| `group` | dimensi | `GROUP BY` |
| `aggregate` | `count`, `count_distinct`, `sum`, `avg`, `min`, `max`, + `where` (FILTER) | `count(*) FILTER (WHERE ...)` |
| `formula` | ekspresi (§3) di atas kolom yang tersedia | `SELECT expr` di CTE berikutnya |
| `condition` | `case` bertingkat | `CASE WHEN ... END` |
| `classify` | binning ke label | `CASE` |
| `stat` | `median`, `percentile(p)`, `stddev`, `variance`, `mode` | `percentile_cont(p) WITHIN GROUP`, `stddev_samp` |
| `window` | `rank`, `row_number`, `share_of_total`, `running_sum`, `lag` | window function |
| `sort`, `limit` | urut & batasi | `ORDER BY`, `LIMIT` |
| `union` | gabungkan output process lain dengan skema sama | `UNION ALL` |
| `use_process` | pakai output process lain sebagai source (reuse) | CTE bersarang, maksimal kedalaman 3 |

Field virtual yang tersedia di setiap source: `_id`, `_created_at`, `_updated_at`, `_owner_org`, `_state` (workflow), `_entity_version`.

### 2.3 Kompilasi

```text
DSL JSON ──► JsonSchema validate ──► AST (readonly classes)
        ──► TypeChecker (resolusi field via metadata, cek tipe numerik/tanggal, kolom tersedia per step)
        ──► Compiler (1 CTE per step yang mengubah bentuk, pakai Query Builder + bindings)
        ──► Explain guard (EXPLAIN (FORMAT JSON); tolak jika estimasi baris > batas)
        ──► Execute (SET LOCAL statement_timeout, scope akses owner process atau user)
```

- Akses field JSONB dikompilasi menjadi `(r.data->>?)::<sqlCast>` dengan key sebagai **binding**. Tipe cast diambil dari `FieldType::sqlCast()`.
- Process selalu dijalankan dengan **scope**: saat preview memakai scope user, saat snapshot indikator memakai scope owner indikator (`SystemContext` yang dicatat di `process_runs`).
- Hasil kompilasi (SQL + urutan binding) di-cache per `process_version_id`.

## 3. Bahasa formula

### 3.1 Tata bahasa (subset aman)

```text
expr     := or
or       := and ( "or" and )*
and      := not ( "and" not )*
not      := "not" not | cmp
cmp      := sum ( ("=" | "!=" | "<" | "<=" | ">" | ">=") sum )?
sum      := prod ( ("+" | "-") prod )*
prod     := unary ( ("*" | "/" | "%") unary )*
unary    := "-" unary | atom
atom     := number | string | "true" | "false" | "null"
          | identifier                      // kolom yang tersedia di step ini
          | func "(" args? ")"
          | "(" expr ")"
```

Fungsi whitelist: `round(x, n)`, `floor`, `ceil`, `abs`, `coalesce(a, b, ...)`, `safe_div(a, b)` (NULL jika b = 0), `nullif`, `least`, `greatest`, `if(cond, a, b)`, `year(date)`, `month(date)`, `days_between(a, b)`, `concat(...)`, `lower`, `upper`, `length`.

### 3.2 Implementasi

- Lexer + parser Pratt ditulis sendiri (± 400 baris) → AST → `SqlEmitter` (untuk process) dan `PhpEvaluator`/`TsEvaluator` (untuk `visible_when` dan guard workflow pada satu record).
- **Tidak memakai `eval`, `symfony/expression-language`, atau string SQL gabungan.** Alasannya: expression-language mengevaluasi PHP dan terlalu permisif (akses method objek), sedangkan kita butuh keluaran SQL yang terjamin aman.
- Literal string/angka dikirim sebagai binding. Identifier hanya boleh berasal dari daftar kolom step sebelumnya.
- Batas: panjang ekspresi ≤ 2.000 karakter, kedalaman AST ≤ 32.
- Tes wajib: *property-based test* bahwa hasil `SqlEmitter` = hasil `PhpEvaluator` untuk input acak.

## 4. Eksekusi & batas sumber daya

| Mode | Pemicu | Timeout | Batas output |
|---|---|---|---|
| Preview (editor) | admin | 10 dtk | 500 baris |
| Snapshot indikator | schedule / event | 5 mnt | tanpa batas (disimpan) |
| Data product (on-demand) | API | 15 dtk | paginasi, 10.000 per halaman |
| Export | user | 10 mnt (job) | chunk 5.000 |

Run dijalankan di queue `processing` dengan jumlah worker terbatas (mis. 2), supaya tidak mengganggu queue `default`.

## 5. Indicator

### 5.1 Definisi (contoh)

```yaml
code: persentase_dataset_dipublikasikan
name: Persentase Dataset Dipublikasikan
owner: Diskominfo
version: 2
process: satudata.publication_rate@v3
value_column: publication_rate
numerator: published
denominator: registered
dimension_columns: [opd]
period_grain: year
unit: "%"
decimals: 2
direction: higher_better
definition_text: >
  Jumlah dataset berstatus published dibagi jumlah dataset terdaftar
  pada tahun berjalan, dikali 100.
```

### 5.2 Snapshot & lineage

```text
event record.updated (entity sumber process X)
   → debounce 5 menit (lock Redis per indicator_version)
   → ComputeIndicator job
        run = process_runs.create(params = {year}, source_watermark = max(updated_at))
        rows = execute(process X)
        UPSERT indicator_values (… , process_run_id = run.id)   -- periode belum final saja
        hapus baris dimensi yang tidak lagi muncul (periode belum final)
        outbox: indicator.updated
```

- **Periode final.** Setelah periode ditutup (mis. tahun anggaran `closed`), nilai dikunci (`is_final = true`) dan tidak dihitung ulang, walaupun data sumber berubah. Perubahan data sumber setelah itu memunculkan flag "data sumber berubah setelah finalisasi" untuk ditinjau owner.
- **Versi indikator.** Kalau formula berubah, dibuat `indicator_version` baru. Nilai versi lama tetap ada, dan dashboard menampilkan versi yang dipilih (default: terbaru). Dengan begitu, angka historis tidak berubah diam-diam.
- **Lineage.** Dari satu nilai bisa ditelusuri `process_run` → `process_version` (DSL) → entity sumber + watermark. UI menyediakan tombol "Asal angka ini".

### 5.3 Konsumsi

- Dashboard/View `kpi|bar|line` hanya membaca `indicator_values` + `indicator_targets`. **Tidak menjalankan process.**
- Capaian dihitung saat baca: `value / target_value` sesuai `direction`.
- Satu indikator bisa dipakai banyak aplikasi. Aksesnya diatur `indicators.visibility` dan grant.

## 6. Kaitan dengan dokumen sumber

Urutan konseptual dari rencana awal (Validation → Normalization → Filter → Join → Group → Aggregate → Calculate → Condition → Classification → Statistical Analysis → Indicator) dipetakan sebagai berikut:
- Validation & Normalization terjadi **saat input** (FieldType::cast), bukan saat processing, supaya data sumber sudah bersih.
- Filter … Classification → step DSL.
- Statistical Analysis dasar → step `stat`/`window`. Statistik lanjutan dan forecasting → M14 (Python worker membaca output process via API internal).
