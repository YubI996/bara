<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Data;

use App\Shared\Data\DataClassification;

final readonly class FieldInput
{
    /** @param  array<string, mixed>  $config */
    public function __construct(
        public string $code,
        public string $label,
        public ?string $helpText,
        public string $type,
        public bool $required,
        public bool $unique,
        public bool $indexed,
        public bool $searchable,
        public DataClassification $classification,
        public array $config,
    ) {}
}
