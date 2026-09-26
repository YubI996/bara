<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

interface SchemaRepository
{
    /** Skema terbit untuk aplikasi aktif; null bila tidak ada / belum terbit / aplikasi tidak aktif. */
    public function published(string $applicationCode, string $entityCode): ?EntitySchema;

    /** Skema terbit berdasarkan id entity (mis. target relasi). */
    public function publishedById(string $entityId): ?EntitySchema;

    /**
     * Semua entity terbit dari aplikasi aktif (untuk menu data).
     *
     * @return list<EntitySchema>
     */
    public function allPublished(): array;
}
