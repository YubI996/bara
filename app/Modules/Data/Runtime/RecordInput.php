<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use Illuminate\Http\UploadedFile;

/** Hasil validasi payload record, berkunci field_key (ADR 0014). */
final readonly class RecordInput
{
    /**
     * @param  array<string, mixed>  $values  nilai skalar/array untuk JSONB
     * @param  array<string, list<string>>  $links  field_key => id objek target
     * @param  array<string, list<string>>  $keptFiles  field_key => id file lama yang dipertahankan
     * @param  array<string, list<UploadedFile>>  $uploads  field_key => berkas baru
     */
    public function __construct(
        public array $values,
        public array $links,
        public array $keptFiles,
        public array $uploads,
    ) {}
}
