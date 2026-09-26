<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Models\User;
use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Validasi server-side payload record (docs/06 §3). Server adalah sumber kebenaran:
 * - kunci tak dikenal ditolak (bukan diabaikan diam-diam), docs/05 §4 kontrol 3;
 * - field di atas clearance user tidak bisa diisi;
 * - nilai dinormalkan (angka format Indonesia, waktu lokal → UTC) sebelum disimpan.
 */
final readonly class RecordValidator
{
    /** Tipe yang belum didukung runtime M2 (data wilayah hadir di M4). */
    public const array DEFERRED_TYPES = ['region'];

    private const array NUMERIC_TYPES = ['integer', 'decimal', 'money', 'percentage'];

    public function __construct(
        private FieldTypeRegistry $types,
        private ValidatorFactory $validator,
        private RelationTargets $targets,
        private RichTextSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<mixed>  $data  payload `data` berkunci kode field
     * @param  array<mixed>  $files  payload `files` berkunci kode field
     */
    public function validate(EntitySchema $schema, FieldGate $gate, User $user, array $data, array $files): RecordInput
    {
        $writable = [];
        foreach ($schema->fields as $field) {
            if ($gate->canWrite($field) && ! in_array($field->type, self::DEFERRED_TYPES, true)) {
                $writable[$field->code] = $field;
            }
        }

        $unknown = [];
        foreach (array_keys($data + $files) as $code) {
            if (! is_string($code) || ! isset($writable[$code])) {
                $unknown[] = (string) $code;
            }
        }
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'data' => 'Field tidak dikenal atau tidak boleh diisi: '.implode(', ', array_slice($unknown, 0, 10)).'.',
            ]);
        }

        $payload = [];
        $rules = [];
        $attributes = [];

        foreach ($writable as $code => $field) {
            $value = $this->prepare($field, $data[$code] ?? null);
            $type = $this->types->get($field->type);

            if ($field->type === 'file') {
                $payload['data'][$code] = is_array($value) ? array_values($value) : [];
                $payload['files'][$code] = is_array($files[$code] ?? null) ? array_values($files[$code]) : [];
                $rules["data.{$code}"] = ['array'];
                $rules["data.{$code}.*"] = ['uuid', 'distinct'];
                $rules["files.{$code}"] = ['array'];
                $rules["files.{$code}.*"] = $this->uploadRules($field);
                $attributes["files.{$code}.*"] = $field->label;
            } else {
                $payload['data'][$code] = $value;
                foreach ($type->valueRules($field) as $suffix => $list) {
                    $rules["data.{$code}{$suffix}"] = $list;
                }
            }

            $attributes["data.{$code}"] = $field->label;
            $attributes["data.{$code}.*"] = $field->label;
        }

        $check = $this->validator->make($payload, $rules, [], $attributes);
        $errors = $check->errors()->toArray();

        $values = [];
        $links = [];
        $kept = [];
        $uploads = [];

        foreach ($writable as $code => $field) {
            if (isset($errors["data.{$code}"])) {
                continue;
            }

            $value = $payload['data'][$code] ?? null;

            if ($field->type === 'relationship') {
                $ids = is_array($value) ? $value : ($value === null ? [] : [$value]);
                $ids = array_values(array_filter($ids, 'is_string'));
                $target = $field->configValue('target_entity_id');
                $visible = is_string($target) ? $this->targets->visible($user, $target, $ids) : [];

                if (count($visible) !== count(array_unique($ids))) {
                    $errors["data.{$code}"] = ["{$field->label} merujuk data yang tidak ditemukan atau di luar kewenangan Anda."];

                    continue;
                }
                $links[$field->fieldKey] = $visible;

                continue;
            }

            if ($field->type === 'file') {
                $keptIds = array_values(array_filter(is_array($value) ? $value : [], 'is_string'));
                $new = array_values(array_filter($payload['files'][$code] ?? [], fn ($f): bool => $f instanceof UploadedFile));
                $max = is_int($field->configValue('max_files')) ? $field->configValue('max_files') : 1;
                $total = count($keptIds) + count($new);

                if ($total > $max) {
                    $errors["data.{$code}"] = ["{$field->label} maksimal {$max} berkas."];
                } elseif ($field->required && $total === 0) {
                    $errors["data.{$code}"] = ["{$field->label} wajib diisi."];
                }
                $kept[$field->fieldKey] = $keptIds;
                $uploads[$field->fieldKey] = $new;

                continue;
            }

            $values[$field->fieldKey] = $this->castValue($field, $value);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new RecordInput($values, $links, $kept, $uploads);
    }

    /** Normalisasi masukan form sebelum divalidasi. */
    private function prepare(FieldDefinition $field, mixed $value): mixed
    {
        if (is_string($value) && trim($value) === '' && $field->type !== 'rich_text') {
            return null;
        }

        return match (true) {
            in_array($field->type, self::NUMERIC_TYPES, true) => $field->type === 'integer'
                ? $this->integer(NumberNormalizer::normalize($value))
                : NumberNormalizer::normalize($value),
            $field->type === 'boolean' => $value === null ? ($field->required ? null : false) : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            $field->type === 'multi_enum' => $value === null ? [] : $value,
            $field->type === 'relationship' && ($field->config['cardinality'] ?? null) === 'many_to_many' => $value === null ? [] : $value,
            is_string($value) => trim($value),
            default => $value,
        };
    }

    private function integer(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value;
    }

    private function castValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            'rich_text' => is_string($value) ? $this->sanitizer->sanitize($value) : null,
            'datetime' => $this->utc($value),
            'multi_enum' => is_array($value) ? array_values(array_unique(array_filter($value, 'is_string'))) : [],
            default => $this->types->get($field->type)->cast($value),
        };
    }

    /** Waktu dari form (zona Pemda, tanpa offset) → ISO-8601 UTC. */
    private function utc(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, config()->string('bara.pemda.timezone'))->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<mixed> */
    private function uploadRules(FieldDefinition $field): array
    {
        $mimes = array_values(array_filter(is_array($field->config['mimes'] ?? null) ? $field->config['mimes'] : [], 'is_string'));
        $maxKb = is_int($field->configValue('max_kb')) ? $field->configValue('max_kb') : 5120;

        // `extensions` memeriksa nama berkas, `mimes` memeriksa isi (deteksi server) — keduanya wajib lolos.
        return ['file', 'max:'.$maxKb, 'extensions:'.implode(',', $mimes), 'mimes:'.implode(',', $mimes)];
    }
}
