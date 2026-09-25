<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

/** Sektor Pentahelix (docs/10). */
enum Sector: string
{
    case Government = 'government';
    case Academia = 'academia';
    case Business = 'business';
    case Community = 'community';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::Government => 'Pemerintah',
            self::Academia => 'Akademisi',
            self::Business => 'Dunia usaha',
            self::Community => 'Komunitas',
            self::Media => 'Media',
        };
    }
}
