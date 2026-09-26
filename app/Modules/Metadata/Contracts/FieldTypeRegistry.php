<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

interface FieldTypeRegistry
{
    public function has(string $code): bool;

    public function get(string $code): FieldType;

    /** @return list<FieldType> */
    public function all(): array;
}
