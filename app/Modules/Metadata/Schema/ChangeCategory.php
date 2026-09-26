<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

/** Kategori perubahan antar-versi (docs/06 §4), urut dari paling ringan. */
enum ChangeCategory: string
{
    case Safe = 'safe';
    case Warning = 'warning';
    case Migration = 'migration';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Safe => 'Aman',
            self::Warning => 'Perlu perhatian',
            self::Migration => 'Migrasi data',
            self::Blocked => 'Ditolak',
        };
    }

    public function severity(): int
    {
        return match ($this) {
            self::Safe => 0,
            self::Warning => 1,
            self::Migration => 2,
            self::Blocked => 3,
        };
    }

    public static function worst(self ...$categories): self
    {
        $worst = self::Safe;
        foreach ($categories as $category) {
            if ($category->severity() > $worst->severity()) {
                $worst = $category;
            }
        }

        return $worst;
    }
}
