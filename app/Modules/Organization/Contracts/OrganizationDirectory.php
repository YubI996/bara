<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

use App\Modules\Access\Contracts\ScopeGrant;

/** Akses baca Core.Organization untuk modul lain (tanpa menyentuh model Eloquent-nya). */
interface OrganizationDirectory
{
    public function find(string $id): ?OrganizationSummary;

    /**
     * Unit aktif yang tercakup grant, urut hierarki.
     *
     * @param  list<ScopeGrant>  $grants
     * @return list<OrganizationSummary>
     */
    public function activeWithin(array $grants): array;

    /** Unit akar (Pemda). Pemilik master data bersama. */
    public function root(): OrganizationSummary;

    /** Tautkan unit ke kode wilayah Kemendagri (core_regions.id). */
    public function linkRegion(string $organizationId, ?string $regionId): void;
}
