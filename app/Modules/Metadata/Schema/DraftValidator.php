<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Shared\Data\DataClassification;

/**
 * Pemeriksaan menyeluruh draft entity (docs/06 §2 langkah 1–3).
 */
final readonly class DraftValidator
{
    public const int MAX_INDEXED_FIELDS = 8;

    public const int MAX_FIELDS = 150;

    /** Kata kunci label yang mengindikasikan data pribadi (UU 27/2022 Pasal 4). */
    private const array PERSONAL_HINTS = [
        'nik', 'nomor induk kependudukan', 'kk', 'kartu keluarga', 'npwp', 'paspor', 'tanggal lahir',
        'tempat lahir', 'alamat', 'telepon', 'nomor hp', 'no hp', 'whatsapp', 'email', 'agama',
        'kesehatan', 'penyakit', 'diagnosa', 'disabilitas', 'biometrik', 'sidik jari', 'anak',
        'rekening', 'gaji', 'penghasilan', 'catatan kejahatan', 'orientasi', 'nama ibu',
    ];

    public function __construct(
        private FieldConfigValidator $fieldValidator,
        private ChangeClassifier $classifier,
    ) {}

    /**
     * @param  list<FieldDefinition>  $draft
     * @param  list<FieldDefinition>  $published
     */
    public function check(EntityContext $entity, array $draft, array $published, RelationshipTargets $targets): DraftReport
    {
        $errors = [];
        $warnings = [];

        if ($draft === []) {
            $errors[] = 'Entity harus punya minimal satu field.';
        }
        if (count($draft) > self::MAX_FIELDS) {
            $errors[] = 'Maksimal '.self::MAX_FIELDS.' field per entity.';
        }

        $labels = [];
        $indexed = 0;

        foreach ($draft as $field) {
            $result = $this->fieldValidator->validate($field, $field->config);
            foreach ($result['errors'] as $message) {
                $errors[] = "{$field->label}: {$message}";
            }

            $labelKey = mb_strtolower(trim($field->label));
            if (isset($labels[$labelKey])) {
                $errors[] = "Label \"{$field->label}\" dipakai lebih dari satu field. Label harus unik agar form jelas bagi pembaca layar.";
            }
            $labels[$labelKey] = true;

            if ($field->indexed) {
                $indexed++;
            }

            if ($field->type === 'relationship') {
                $error = $this->relationshipError($entity, $field, $targets);
                if ($error !== null) {
                    $errors[] = "{$field->label}: {$error}";
                }
            }

            if (! $field->classification->isPersonal() && $this->looksPersonal($field)) {
                $warnings[] = "{$field->label}: label mengindikasikan data pribadi. Periksa apakah klasifikasinya harus \"Data pribadi\".";
            }
        }

        if ($indexed > self::MAX_INDEXED_FIELDS) {
            $errors[] = 'Maksimal '.self::MAX_INDEXED_FIELDS.' field diindeks per entity (ADR 0004). Pertimbangkan promosi ke tabel fisik.';
        }

        foreach ($this->titleTemplateErrors($entity->titleTemplate, $draft) as $error) {
            $errors[] = $error;
        }

        $changes = $this->classifier->classify($published, $draft);
        $hasChanges = $published === [] || $changes !== [];

        return new DraftReport(
            $errors,
            $warnings,
            $changes,
            $hasChanges && $draft !== [],
            $this->requiresPrivacyReview($published, $draft),
        );
    }

    /**
     * Perlu persetujuan Pejabat PDP bila ada field data pribadi baru, klasifikasi dinaikkan
     * ke data pribadi, atau diturunkan dari data pribadi.
     *
     * @param  list<FieldDefinition>  $published
     * @param  list<FieldDefinition>  $draft
     */
    public function requiresPrivacyReview(array $published, array $draft): bool
    {
        $before = [];
        foreach ($published as $field) {
            $before[$field->fieldKey] = $field->classification;
        }

        foreach ($draft as $field) {
            $old = $before[$field->fieldKey] ?? null;
            if ($field->classification->isPersonal() && $old !== $field->classification) {
                return true;
            }
            if ($old instanceof DataClassification && $old->isPersonal() && ! $field->classification->isPersonal()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Placeholder {kode} pada template judul harus menunjuk field skalar yang ada.
     *
     * @param  list<FieldDefinition>  $fields
     * @return list<string>
     */
    public function titleTemplateErrors(string $template, array $fields): array
    {
        if (trim($template) === '') {
            return [];
        }

        if (mb_strlen($template) > 200) {
            return ['Template judul maksimal 200 karakter.'];
        }

        preg_match_all('/\{([^{}]*)\}/', $template, $matches);
        $scalar = [];
        foreach ($fields as $field) {
            if (! in_array($field->type, ['relationship', 'file', 'multi_enum', 'rich_text'], true)) {
                $scalar[$field->code] = true;
            }
        }

        $errors = [];
        foreach ($matches[1] as $code) {
            if (! isset($scalar[$code])) {
                $errors[] = "Template judul memakai {{$code}} yang bukan kode field teks/angka/tanggal/pilihan pada entity ini.";
            }
        }

        if ($matches[1] === []) {
            $errors[] = 'Template judul harus memuat minimal satu {kode_field}, misalnya {nama}.';
        }

        return $errors;
    }

    private function relationshipError(EntityContext $entity, FieldDefinition $field, RelationshipTargets $targets): ?string
    {
        $targetId = $field->configValue('target_entity_id');

        if (! is_string($targetId)) {
            return 'Target relasi belum dipilih.';
        }

        if ($targetId === $entity->id) {
            return null; // relasi ke diri sendiri (hierarki), mis. parent_issue
        }

        $target = $targets->find($targetId);

        if ($target === null) {
            return 'Entity target tidak ditemukan.';
        }
        if ($target->applicationId !== $entity->applicationId && ! $target->isShared) {
            return "Entity {$target->label()} milik aplikasi lain dan tidak dibagikan (shared).";
        }
        if (! $target->isPublished) {
            return "Entity {$target->label()} belum pernah dipublikasikan.";
        }

        return null;
    }

    private function looksPersonal(FieldDefinition $field): bool
    {
        $haystack = ' '.mb_strtolower($field->label.' '.str_replace('_', ' ', $field->code)).' ';

        foreach (self::PERSONAL_HINTS as $hint) {
            if (preg_match('/(?<![a-z])'.preg_quote($hint, '/').'(?![a-z])/u', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }
}
