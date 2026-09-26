<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

interface RelationshipTargets
{
    public function find(string $entityId): ?RelationshipTarget;

    /**
     * Target yang boleh dipilih entity dalam aplikasi ini: entity satu aplikasi + entity bersama
     * yang sudah terbit. (Pendaftaran consumer lintas aplikasi menyusul di M4.)
     *
     * @return list<RelationshipTarget>
     */
    public function selectableFor(string $applicationId): array;
}
