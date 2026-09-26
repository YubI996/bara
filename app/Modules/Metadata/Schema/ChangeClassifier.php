<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;

/**
 * Membandingkan field draft dengan versi terbit dan mengklasifikasi tiap perubahan (docs/06 §4).
 * Identitas field = field_key, sehingga ganti kode tidak dianggap hapus + tambah.
 */
final readonly class ChangeClassifier
{
    /** Perubahan tipe yang datanya bisa dikonversi otomatis. */
    private const array COMPATIBLE_TYPE_CHANGES = [
        'integer' => ['decimal', 'money'],
        'decimal' => ['money'],
        'string' => ['text'],
        'enum' => ['multi_enum', 'string'],
    ];

    public function __construct(private FieldTypeRegistry $types) {}

    /**
     * @param  list<FieldDefinition>  $previous  field versi terbit (kosong = publikasi pertama)
     * @param  list<FieldDefinition>  $draft
     * @return list<FieldChange> hanya field yang berubah
     */
    public function classify(array $previous, array $draft): array
    {
        $before = [];
        foreach ($previous as $field) {
            $before[$field->fieldKey] = $field;
        }

        $changes = [];
        $isFirst = $previous === [];

        foreach ($draft as $field) {
            $old = $before[$field->fieldKey] ?? null;
            unset($before[$field->fieldKey]);

            $change = $old === null ? $this->added($field, $isFirst) : $this->modified($old, $field);
            if ($change !== null) {
                $changes[] = $change;
            }
        }

        foreach ($before as $removed) {
            $changes[] = new FieldChange($removed->fieldKey, $removed->code, $removed->label, 'removed', ChangeCategory::Warning, [
                'Field disembunyikan dari form. Data lama tetap tersimpan dan tampil sebagai data historis.',
            ]);
        }

        return $changes;
    }

    /** @param  list<FieldChange>  $changes */
    public static function worst(array $changes): ChangeCategory
    {
        return ChangeCategory::worst(...array_map(static fn (FieldChange $c): ChangeCategory => $c->category, $changes));
    }

    private function added(FieldDefinition $field, bool $isFirst): FieldChange
    {
        if ($isFirst || ! $field->required) {
            return new FieldChange($field->fieldKey, $field->code, $field->label, 'added', ChangeCategory::Safe, [
                $field->required ? 'Field wajib baru.' : 'Field opsional baru; data lama bernilai kosong.',
            ]);
        }

        if ($this->types->get($field->type)->supportsDefault() && array_key_exists('default', $field->config)) {
            return new FieldChange($field->fieldKey, $field->code, $field->label, 'added', ChangeCategory::Migration, [
                'Field wajib baru; nilai default akan diisikan ke data lama.',
            ]);
        }

        return new FieldChange($field->fieldKey, $field->code, $field->label, 'added', ChangeCategory::Blocked, [
            'Field wajib baru butuh nilai default karena data lama belum punya nilai. Isi default atau jadikan opsional.',
        ]);
    }

    private function modified(FieldDefinition $old, FieldDefinition $new): ?FieldChange
    {
        /** @var list<array{ChangeCategory, string}> $notes */
        $notes = [];

        if ($old->type !== $new->type) {
            $compatible = in_array($new->type, self::COMPATIBLE_TYPE_CHANGES[$old->type] ?? [], true);
            $from = $this->types->get($old->type)->label();
            $to = $this->types->get($new->type)->label();
            $notes[] = $compatible
                ? [ChangeCategory::Migration, "Tipe berubah dari {$from} ke {$to}; data lama dikonversi otomatis."]
                : [ChangeCategory::Blocked, "Tipe tidak bisa diubah dari {$from} ke {$to}. Buat field baru, lalu hapus yang lama."];
        }

        if ($old->code !== $new->code) {
            $notes[] = [ChangeCategory::Safe, "Kode berubah dari {$old->code} ke {$new->code}; data dipetakan lewat field_key."];
        }
        if ($old->label !== $new->label || $old->helpText !== $new->helpText) {
            $notes[] = [ChangeCategory::Safe, 'Label atau teks bantuan berubah.'];
        }
        if ($old->position !== $new->position) {
            $notes[] = [ChangeCategory::Safe, 'Urutan tampil berubah.'];
        }
        if (! $old->required && $new->required) {
            $notes[] = [ChangeCategory::Warning, 'Menjadi wajib: data lama yang kosong tetap sah sampai diedit.'];
        }
        if ($old->required && ! $new->required) {
            $notes[] = [ChangeCategory::Safe, 'Menjadi opsional.'];
        }
        if (! $old->unique && $new->unique) {
            $notes[] = [ChangeCategory::Warning, 'Menjadi unik: publikasi index gagal bila data lama punya nilai ganda.'];
        }
        if ($old->indexed !== $new->indexed || $old->searchable !== $new->searchable || ($old->unique && ! $new->unique)) {
            $notes[] = [ChangeCategory::Safe, 'Pengaturan index/pencarian berubah.'];
        }
        if ($old->classification !== $new->classification) {
            $notes[] = [ChangeCategory::Warning, sprintf(
                'Klasifikasi data berubah dari %s ke %s.',
                $old->classification->label(),
                $new->classification->label(),
            )];
        }

        if ($old->type === $new->type) {
            foreach ($this->configNotes($old, $new) as $note) {
                $notes[] = $note;
            }
        }

        if ($notes === []) {
            return null;
        }

        return new FieldChange(
            $new->fieldKey,
            $new->code,
            $new->label,
            'modified',
            ChangeCategory::worst(...array_column($notes, 0)),
            array_column($notes, 1),
        );
    }

    /** @return list<array{ChangeCategory, string}> */
    private function configNotes(FieldDefinition $old, FieldDefinition $new): array
    {
        if ($old->config === $new->config) {
            return [];
        }

        $notes = [];
        $a = $old->config;
        $b = $new->config;

        if ($new->type === 'relationship') {
            if (($a['target_entity_id'] ?? null) !== ($b['target_entity_id'] ?? null)) {
                return [[ChangeCategory::Blocked, 'Target relasi tidak bisa diganti. Buat field relasi baru.']];
            }
            if (($a['cardinality'] ?? null) === 'many_to_many' && ($b['cardinality'] ?? null) === 'many_to_one') {
                return [[ChangeCategory::Blocked, 'Relasi banyak-ke-banyak tidak bisa diubah menjadi banyak-ke-satu.']];
            }
            if (($a['cardinality'] ?? null) !== ($b['cardinality'] ?? null)) {
                $notes[] = [ChangeCategory::Migration, 'Relasi menjadi banyak-ke-banyak.'];
            }
        }

        if (in_array($new->type, ['enum', 'multi_enum'], true)) {
            $removed = array_diff($this->optionValues($a), $this->optionValues($b));
            if ($removed !== []) {
                $notes[] = [ChangeCategory::Warning, 'Opsi dihapus: '.implode(', ', $removed).'. Data lama dengan nilai itu tetap ada.'];
            }
        }

        if ($this->tightened($a, $b)) {
            $notes[] = [ChangeCategory::Warning, 'Validasi diperketat: berlaku saat data lama diedit.'];
        }

        if ($notes === []) {
            $notes[] = [ChangeCategory::Safe, 'Pengaturan field berubah.'];
        }

        return $notes;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function tightened(array $a, array $b): bool
    {
        $num = static fn (mixed $v): ?float => is_numeric($v) ? (float) $v : null;

        foreach (['max_length', 'max', 'max_kb', 'max_files', 'scale', 'max_selected', 'level_max'] as $key) {
            $old = $num($a[$key] ?? null);
            $new = $num($b[$key] ?? null);
            if ($new !== null && ($old === null || $new < $old)) {
                return true;
            }
        }

        foreach (['min', 'level_min'] as $key) {
            $old = $num($a[$key] ?? null);
            $new = $num($b[$key] ?? null);
            if ($new !== null && ($old === null || $new > $old)) {
                return true;
            }
        }

        return ($a['pattern'] ?? null) !== ($b['pattern'] ?? null) && isset($b['pattern']);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function optionValues(array $config): array
    {
        $values = [];
        foreach (is_array($config['options'] ?? null) ? $config['options'] : [] as $option) {
            if (is_array($option) && is_string($option['value'] ?? null)) {
                $values[] = $option['value'];
            }
        }

        return $values;
    }
}
