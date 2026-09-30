<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Support;

final readonly class PersonData
{
    /** @param  string|null  $nik  null = tidak diubah (edit) / tidak ada (baru); '' = hapus */
    public function __construct(
        public string $fullName,
        public ?string $nik,
        public ?string $birthDate,
        public ?string $email,
        public ?string $phone,
    ) {}
}
