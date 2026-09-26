<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure;

use App\Modules\Organization\Contracts\OrganizationDirectory;
use App\Modules\Organization\Contracts\OrganizationSummary;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Str;

final readonly class EloquentOrganizationDirectory implements OrganizationDirectory
{
    public function __construct(private OrganizationQuery $query) {}

    public function find(string $id): ?OrganizationSummary
    {
        $org = Str::isUuid($id) ? Organization::query()->find($id) : null;

        return $org === null ? null : $this->summarize($org);
    }

    public function activeWithin(array $grants): array
    {
        return array_values($this->query->within($grants, activeOnly: true)
            ->map(fn (Organization $org): OrganizationSummary => $this->summarize($org))
            ->all());
    }

    private function summarize(Organization $org): OrganizationSummary
    {
        return new OrganizationSummary($org->id, $org->code, $org->name, $org->path, $org->depth(), $org->isActive());
    }
}
