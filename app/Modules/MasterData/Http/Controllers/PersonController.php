<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\MasterData\Actions\AddEmployment;
use App\Modules\MasterData\Actions\EndEmployment;
use App\Modules\MasterData\Actions\RevealNik;
use App\Modules\MasterData\Actions\SavePerson;
use App\Modules\MasterData\Http\Requests\PersonRequest;
use App\Modules\MasterData\Infrastructure\PersonQueries;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use App\Modules\Organization\Contracts\OrganizationSummary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Master data orang & pegawai (Core.Person/Employee), dikelola Walidata. */
final readonly class PersonController
{
    public function __construct(
        private AccessChecker $access,
        private PersonQueries $queries,
        private OrganizationDirectory $organizations,
    ) {}

    /**
     * Daftar & pencarian. Pencarian memakai POST supaya NIK tidak pernah masuk URL (riwayat
     * browser, log akses server/proxy); respons berupa halaman, bukan redirect.
     */
    public function index(Request $request): Response
    {
        $user = $this->ensure($request, PlatformPermission::MasterDataView);
        $term = $request->isMethod('post') ? $request->string('q')->limit(100, '')->toString() : '';
        $term = preg_replace('/\s+/', ' ', trim($term)) ?? '';
        // NIK yang ditempel dengan spasi/titik tetap dikenali.
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $term = strlen($digits) === 16 ? $digits : $term;

        return Inertia::render('admin/master-data/persons/index', [
            'persons' => $this->queries->search($term),
            // Kolom pencarian tidak pernah memantulkan NIK lengkap kembali ke halaman.
            'q' => strlen($digits) === 16 ? '' : $term,
            'searched_nik' => strlen($digits) === 16,
            'limit' => PersonQueries::LIMIT,
            'can' => ['manage' => $this->access->hasAnywhere($user, PlatformPermission::MasterDataManage)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensure($request, PlatformPermission::MasterDataManage);

        return Inertia::render('admin/master-data/persons/form', ['person' => null]);
    }

    public function store(PersonRequest $request, SavePerson $action): RedirectResponse
    {
        $id = $action->execute($request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data orang tersimpan.']);

        return to_route('admin.master-data.persons.show', $id);
    }

    public function show(Request $request, string $person): Response
    {
        return $this->render($this->ensure($request, PlatformPermission::MasterDataView), $person);
    }

    public function edit(Request $request, string $person): Response
    {
        $this->ensure($request, PlatformPermission::MasterDataManage);

        return Inertia::render('admin/master-data/persons/form', ['person' => $this->findOrFail($person)]);
    }

    public function update(PersonRequest $request, string $person, SavePerson $action): RedirectResponse
    {
        $this->findOrFail($person);
        $action->execute($request->toData(), $person);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data orang diperbarui.']);

        return to_route('admin.master-data.persons.show', $person);
    }

    /**
     * Respons berupa halaman (bukan redirect) agar NIK tidak pernah tersimpan di sesi/flash.
     * Rute ini juga di balik `password.confirm`.
     */
    public function reveal(Request $request, string $person, RevealNik $action): Response
    {
        $user = $this->ensure($request, PlatformPermission::PiiReveal);
        $this->findOrFail($person);
        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']], [], ['reason' => 'alasan']);
        $nik = $action->execute($person, $user, $request->string('reason')->toString());

        return $this->render($user, $person, $nik);
    }

    public function afterPasswordConfirm(string $person): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kata sandi terkonfirmasi. Isi alasan lalu tekan “Buka NIK” sekali lagi.']);

        return to_route('admin.master-data.persons.show', $person);
    }

    public function addEmployment(Request $request, string $person, AddEmployment $action): RedirectResponse
    {
        $this->ensure($request, PlatformPermission::MasterDataManage);
        $this->findOrFail($person);
        $request->validate([
            'org_id' => ['required', 'uuid'],
            'nip' => ['nullable', 'digits:18'],
            'position' => ['nullable', 'string', 'max:150'],
            'rank' => ['nullable', 'string', 'max:60'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
        ], [], ['org_id' => 'unit', 'nip' => 'NIP', 'position' => 'jabatan', 'rank' => 'pangkat/golongan', 'valid_from' => 'mulai bertugas']);

        $optional = fn (string $key): ?string => $request->filled($key) ? $request->string($key)->trim()->toString() : null;
        $action->execute($person, $request->string('org_id')->toString(), $optional('nip'), $optional('position'), $optional('rank'), $request->string('valid_from')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penugasan ditambahkan.']);

        return to_route('admin.master-data.persons.show', $person);
    }

    public function endEmployment(Request $request, string $person, string $employee, EndEmployment $action): RedirectResponse
    {
        $this->ensure($request, PlatformPermission::MasterDataManage);
        $request->validate(['valid_to' => ['required', 'date_format:Y-m-d']], [], ['valid_to' => 'tanggal berakhir']);
        $action->execute($employee, $request->string('valid_to')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penugasan diakhiri.']);

        return to_route('admin.master-data.persons.show', $person);
    }

    private function render(User $user, string $person, ?string $revealedNik = null): Response
    {
        $orgs = $this->access->hasAnywhere($user, PlatformPermission::MasterDataManage)
            ? array_map(
                static fn (OrganizationSummary $o): array => ['value' => $o->id, 'label' => $o->name, 'depth' => $o->depth],
                $this->organizations->activeWithin($this->access->grants($user, PlatformPermission::MasterDataManage)),
            )
            : [];

        return Inertia::render('admin/master-data/persons/show', [
            'person' => $this->findOrFail($person),
            'revealed_nik' => $revealedNik,
            'organizations' => $orgs,
            'can' => [
                'manage' => $this->access->hasAnywhere($user, PlatformPermission::MasterDataManage),
                'reveal' => $this->access->hasAnywhere($user, PlatformPermission::PiiReveal),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function findOrFail(string $person): array
    {
        return $this->queries->find($person) ?? throw new NotFoundHttpException;
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
