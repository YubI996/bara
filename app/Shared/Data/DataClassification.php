<?php

declare(strict_types=1);

namespace App\Shared\Data;

/**
 * Klasifikasi sensitivitas data (docs/02 §B). Dipakai bersama: klasifikasi field (Metadata)
 * dan clearance role (Access). Urutan case = urutan sensitivitas.
 */
enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Restricted = 'restricted';
    case Personal = 'personal';
    case PersonalSpecific = 'personal_specific';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Publik',
            self::Internal => 'Internal',
            self::Restricted => 'Terbatas',
            self::Personal => 'Data pribadi umum',
            self::PersonalSpecific => 'Data pribadi spesifik',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Internal => 1,
            self::Restricted => 2,
            self::Personal => 3,
            self::PersonalSpecific => 4,
        };
    }

    public function isPersonal(): bool
    {
        return $this === self::Personal || $this === self::PersonalSpecific;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $c): array => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
