# 06 — Metadata Engine & Runtime CRUD

## 1. Registry tipe field

Setiap tipe adalah kelas PHP yang mengimplementasikan `FieldType`:

```php
interface FieldType
{
    public function code(): string;                                   // 'integer'
    /** @return array<int, string|\Illuminate\Contracts\Validation\ValidationRule> */
    public function rules(FieldDefinition $field): array;             // aturan validasi Laravel
    public function jsonSchema(FieldDefinition $field): array;        // untuk klien & kontrak API
    public function cast(mixed $value): mixed;                        // normalisasi sebelum simpan
    public function sqlCast(): ?string;                               // 'int','numeric','date', null = text
    public function configSchema(): array;                            // validasi fields.config
    public function uiComponent(): string;                            // 'NumberInput'
}
```

| Type           | Disimpan di JSONB sebagai                      | Config                               | Validasi                | UI                               | `sqlCast`     |
| -------------- | ---------------------------------------------- | ------------------------------------ | ----------------------- | -------------------------------- | ------------- |
| `string`       | string                                         | `max_length` (≤ 500), `pattern`      | `string`, `max`         | TextInput                        | –             |
| `text`         | string                                         | `max_length` (≤ 20000)               | `string`, `max`         | Textarea                         | –             |
| `rich_text`    | string (HTML tersanitasi)                      | allowlist tag                        | sanitasi                | RichText (a11y)                  | –             |
| `integer`      | number                                         | `min`, `max`                         | `integer`, `between`    | NumberInput                      | `bigint`      |
| `decimal`      | string (presisi terjaga)                       | `scale` (≤ 6), `min`, `max`          | `decimal:0,scale`       | NumberInput                      | `numeric`     |
| `money`        | string (Rupiah, 2 desimal)                     | `min`                                | `decimal:0,2`           | MoneyInput                       | `numeric`     |
| `percentage`   | string                                         | `scale`                              | `between:0,100`         | NumberInput + %                  | `numeric`     |
| `boolean`      | boolean                                        | –                                    | `boolean`               | Switch                           | `boolean`     |
| `date`         | string `YYYY-MM-DD`                            | `min`, `max` (bisa relatif: `today`) | `date_format:Y-m-d`     | DatePicker                       | `date`        |
| `datetime`     | string ISO 8601 UTC                            | –                                    | `date`                  | DateTimePicker                   | `timestamptz` |
| `enum`         | string (code)                                  | `options[]` **atau** `codelist`      | `in:` / exists codelist | Select / RadioGroup (≤ 5 opsi)   | –             |
| `multi_enum`   | string[]                                       | sama                                 | `array`, `in:`          | CheckboxGroup / Combobox         | –             |
| `relationship` | **tidak di JSONB**, disimpan di `record_links` | `relationship_id`                    | exists + scope check    | EntitySelector (async search)    | –             |
| `file`         | uuid[] (ke `files`)                            | `mimes`, `max_kb`, `max_files`       | file rules              | FileUpload                       | –             |
| `region`       | uuid (Core.Region)                             | `level` min/max                      | exists                  | Cascading select                 | –             |
| `geo_point`    | `{lat, lng}`                                   | bbox                                 | numeric range           | Map picker + input manual (a11y) | –             |

Semua nilai numerik presisi (`decimal`, `money`, `percentage`) disimpan sebagai **string** di JSONB, lalu di-cast `::numeric` di SQL. Angka JSON (double) tidak dipakai untuk uang, supaya tidak terjadi error pembulatan floating point.

## 2. Kompilasi skema

```text
Entity (draft) + Fields + Relationships
          │   PublishEntityVersion (Action, dalam transaksi)
          ▼
1. Validasi metadata   : code unik, config valid menurut configSchema, relasi target ada & shared/consumer terdaftar
2. Diff vs versi aktif : klasifikasi perubahan (lihat §4)
3. Gate DPO            : ada field personal baru → butuh approval dpo
4. Compile             : compiled_schema = { json_schema, rules, ui, indexes, relationships }
5. Persist             : status published, versi lama → superseded
6. Side-effect (outbox): metadata.published → EnsureFieldIndex, GeneratePermissions, WarmSchemaCache
```

`compiled_schema` bersifat immutable, sehingga runtime cukup membaca satu baris JSON per versi (di-cache Redis dengan key `schema:{entity_version_id}` tanpa TTL).

### Implementasi M1 (yang berbeda/lebih rinci dari rancangan)

- **Isi `compiled_schema`**: `format`, `entity`, `version`, `fields` (definisi lengkap, sumber kebenaran versi terbit), `json_schema` (Draft 2020-12, `additionalProperties: false`), `ui`, `indexes`, `relationships`. Aturan validasi Laravel **tidak** disimpan: aturan diturunkan deterministik dari `fields` lewat `FieldType::valueRules()` saat runtime, karena objek rule tidak bisa diserialisasi dengan aman.
- **Kontrak `FieldType`** (`app/Modules/Metadata/Contracts/FieldType.php`): `configRules()`, `normalizeConfig()`, `configErrors()`, `valueRules()` (kunci `''` dan `'.*'`), `jsonSchema()`, `cast()`, `sqlCast()`, `uiComponent()`, `supportsIndex/Search/Default()`.
- **Relasi** didefinisikan di `fields.config` (`target_entity_id`, `cardinality`, `on_target_delete`, `inverse_code`). Registry `relationships` disinkronkan saat publikasi. Target yang boleh: entity satu aplikasi, entity `is_shared`, atau entity Core fisik. Pendaftaran consumer menyusul di M4.
- **Gate Pejabat PDP**: draft yang menambah field data pribadi, menaikkan klasifikasi ke data pribadi, atau menurunkannya dari data pribadi harus disetujui user dengan permission `platform.privacy.review` (role `dpo`). Persetujuan tersimpan di draft dan **batal otomatis** bila draft diubah. `platform_admin` sengaja tidak memegang permission ini (pemisahan tugas).
- **Pola regex** buatan admin diperiksa `SafeRegex`: maksimal 200 karakter, tanpa kuantifier bersarang dan backreference (mencegah ReDoS).
- **Kode field dicadangkan**: `id`, `title`, `created_at`, `owner_org_id`, dan lainnya (`FieldConfigValidator::RESERVED_CODES`).
- **Ubah tipe tidak kompatibel** selalu ditolak (lebih ketat dari §4). Alasannya: pemeriksaan "data gagal di-cast" baru mungkin setelah record ada (M2).
- **Permission entity** `{app}.{entity}.{view|create|update|delete|export}` didaftarkan setiap publikasi (idempotent). Role aplikasi yang memakainya menyusul di M5.

## 3. Runtime CRUD

### Rute

```text
GET    /apps/{app}/{entity}                   index   (table view default)
GET    /apps/{app}/{entity}/create            form
POST   /apps/{app}/{entity}                   store
GET    /apps/{app}/{entity}/{record}          show
GET    /apps/{app}/{entity}/{record}/edit     form
PUT    /apps/{app}/{entity}/{record}          update  (butuh lock_version)
DELETE /apps/{app}/{entity}/{record}          destroy (soft)
POST   /apps/{app}/{entity}/{record}/actions/{action}   transisi workflow
GET    /apps/{app}/{entity}/lookup?q=         untuk EntitySelector (JSON)
POST   /apps/{app}/{entity}/export            job export (CSV/XLSX)
```

Satu `RecordController` generik. `{app}` dan `{entity}` di-_resolve_ ke `EntityContext` lewat route binding. Kalau tidak ditemukan atau tidak aktif, responsnya 404.

### Action: `CreateRecord`

```text
1. authorize: permission {app}.{entity}.create pada scope owner_org yang dipilih
2. rules    : compiled_schema.rules + unique rules (query index ekspresi)
3. relasi   : setiap target_id → ada di objects, entity cocok, user boleh "reference" target (scope/visibility)
4. BEGIN
   INSERT objects (owner_org_id, owner_path, visibility default entity)
   INSERT records (data = cast(fields), title = render(title_template))
   INSERT record_links
   workflow: INSERT workflow_instances (state awal) jika entity punya workflow
   AuditLogger::record('record.create', diff)
   EventRecorder::record('record.created', {entity, id})
   COMMIT
```

### List & filter

- Filter dari query string divalidasi terhadap field yang `is_indexed` atau `is_searchable`. Field lain tidak bisa difilter, supaya tidak terjadi full scan tanpa sengaja.
- Paginasi cursor `(created_at, id)` untuk tabel besar, dan offset hanya untuk ≤ 10.000 baris.
- Kolom relasi di-_eager load_ per batch (lihat doc 04 §11).

## 4. Evolusi skema (versi)

| Perubahan                                                       | Kategori                                                             | Perlakuan data lama                                                                    |
| --------------------------------------------------------------- | -------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| Tambah field opsional                                           | aman                                                                 | Tidak perlu migrasi. Nilai lama = null.                                                |
| Tambah field wajib                                              | butuh default                                                        | Wajib isi `default` atau jalankan backfill job. Record lama tetap valid sampai diedit. |
| Rename label / help text                                        | aman                                                                 | –                                                                                      |
| Rename `code`                                                   | aman (via `field_key`)                                               | Job menulis ulang key JSONB per batch 5.000 baris                                      |
| Ubah tipe kompatibel (`integer` → `decimal`, `string` → `text`) | migrasi otomatis                                                     | Job cast + validasi                                                                    |
| Ubah tipe tidak kompatibel (`string` → `integer`)               | **ditolak** jika ada data yang gagal di-cast                         | Laporan baris gagal harus diperbaiki dulu                                              |
| Hapus field                                                     | **ditolak** jika dipakai process/indicator/view/workflow guard aktif | Setelah bebas: field disembunyikan, data tetap di JSONB sampai retensi                 |
| Perketat validasi (mis. `max` turun)                            | peringatan                                                           | Record lama tidak dipaksa. Validasi baru berlaku saat diedit.                          |

Record menyimpan `entity_version_id`. Tampilan record lama memakai **versi terbaru**, dan field yang sudah dihapus ditampilkan di bagian "Data historis" (read-only).

**Dependency check** memakai tabel turunan `metadata_dependencies (dependent_type, dependent_id, field_key)` yang diisi saat process/view/indicator dipublikasikan.

## 5. Form layout

```json
{
    "sections": [
        {
            "title": "Identitas Kegiatan",
            "description": "Isi sesuai DPA.",
            "fields": ["activity", "period_month", "location"]
        },
        {
            "title": "Realisasi",
            "fields": ["physical_pct", "budget_realized"],
            "visible_when": {
                "field": "status_kegiatan",
                "op": "in",
                "value": ["berjalan", "selesai"]
            }
        }
    ]
}
```

`visible_when` memakai subset ekspresi formula (doc 07). Ekspresi yang sama dievaluasi di server (field tersembunyi diabaikan dan tidak divalidasi _required_) dan di klien (interpreter TS kecil dari AST yang sama). Tujuannya supaya perilaku server dan klien identik.

## 6. Konsumsi shared entity oleh aplikasi lain

1. App B meminta akses consumer ke `core.organization` atau `bappeda.program` (`entity_consumers`, access `reference`).
2. `data_steward` menyetujui.
3. App B bisa membuat relationship ke entity tersebut. **Tidak bisa** menambah field atau mengubah record-nya.
4. Kebutuhan field tambahan milik App B dimodelkan sebagai **entity ekstensi** di App B (`program_extension` many_to_one → program). Tidak dilakukan dengan menambah kolom di entity milik owner.
