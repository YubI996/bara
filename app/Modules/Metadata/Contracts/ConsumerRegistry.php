<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

/**
 * Pendaftaran consumer entity bersama (docs/04 §3, ADR 0016). Entity milik aplikasi lain
 * (termasuk master data Core) hanya boleh dirujuk setelah Walidata menyetujui aplikasi pemakai.
 */
interface ConsumerRegistry
{
    /** True bila entity milik aplikasi ini sendiri, atau aplikasi ini consumer yang disetujui. */
    public function allows(string $applicationId, string $entityId): bool;
}
