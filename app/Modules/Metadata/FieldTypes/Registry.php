<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldType;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use InvalidArgumentException;

/** Daftar tipe field yang tersedia. Tipe baru wajib lewat ADR (docs/15 R01). */
final class Registry implements FieldTypeRegistry
{
    /** @var array<string, FieldType> */
    private array $types = [];

    public function __construct()
    {
        foreach ([
            new StringType, new TextType, new RichTextType,
            new IntegerType, new DecimalType, new MoneyType, new PercentageType,
            new BooleanType, new DateType, new DateTimeType,
            new EnumType, new MultiEnumType,
            new RelationshipType, new FileType, new RegionType,
        ] as $type) {
            $this->types[$type->code()] = $type;
        }
    }

    public function has(string $code): bool
    {
        return isset($this->types[$code]);
    }

    public function get(string $code): FieldType
    {
        return $this->types[$code] ?? throw new InvalidArgumentException("Tipe field [{$code}] tidak dikenal.");
    }

    public function all(): array
    {
        return array_values($this->types);
    }
}
