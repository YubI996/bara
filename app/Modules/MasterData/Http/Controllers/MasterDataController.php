<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\MasterData\Actions\ChangeFiscalYearStatus;
use App\Modules\MasterData\Actions\CreateFiscalYear;
use App\Modules\MasterData\Http\Requests\FiscalYearRequest;
use App\Modules\MasterData\Infrastructure\MasterDataQueries;
use App\Modules\MasterData\Support\RegionCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Master data bersama (M4): ringkasan, wilayah (baca), tahun anggaran. */
final readonly class MasterDataController
{
    public function __construct(
        private AccessChecker $access,
        private MasterDataQueries $queries,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->ensure($request, PlatformPermission::MasterDataView);

        return Inertia::render('admin/master-data/index', [
            'counts' => $this->queries->counts(),
            'can' => ['manage' => $this->access->hasAnywhere($user, PlatformPermission::MasterDataManage)],
        ]);
    }

    public function regions(Request $request): Response
    {
        $this->ensure($request, PlatformPermission::MasterDataView);
        $parent = $request->string('parent')->toString();
        $term = $request->string('q')->squish()->limit(100, '')->toString();

        return Inertia::render('admin/master-data/regions', [
            ...$this->queries->regions(RegionCode::isValid($parent) ? $parent : null, $term, max(1, $request->integer('page', 1))),
            'q' => $term,
        ]);
    }

    public function fiscalYears(Request $request): Response
    {
        $user = $this->ensure($request, PlatformPermission::MasterDataView);

        return Inertia::render('admin/master-data/fiscal-years', [
            'fiscal_years' => $this->queries->fiscalYears(),
            'can' => ['manage' => $this->access->hasAnywhere($user, PlatformPermission::MasterDataManage)],
        ]);
    }

    public function storeFiscalYear(FiscalYearRequest $request, CreateFiscalYear $action): RedirectResponse
    {
        $action->execute($request->integer('year'), $request->string('starts_on')->toString(), $request->string('ends_on')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Tahun anggaran {$request->integer('year')} ditambahkan."]);

        return to_route('admin.master-data.fiscal-years');
    }

    public function changeFiscalYearStatus(Request $request, string $fiscalYear, ChangeFiscalYearStatus $action): RedirectResponse
    {
        $this->ensure($request, PlatformPermission::MasterDataManage);
        $request->validate(['status' => ['required', 'in:running,closed']]);
        $action->execute($fiscalYear, $request->string('status')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status tahun anggaran diperbarui.']);

        return to_route('admin.master-data.fiscal-years');
    }

    private function ensure(Request $request, PlatformPermission $permission): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->access->hasAnywhere($user, $permission)) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
