<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Metadata\Actions\DecideEntityConsumer;
use App\Modules\Metadata\Actions\RequestEntityConsumer;
use App\Modules\Metadata\Http\Requests\ConsumerRequest;
use App\Modules\Metadata\Infrastructure\ConsumerQueries;
use App\Modules\Metadata\Models\Application;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pendaftaran consumer entity bersama (ADR 0016): admin mengajukan, Walidata memutuskan. */
final readonly class ConsumerController
{
    public function __construct(
        private AccessChecker $access,
        private ConsumerQueries $queries,
    ) {}

    public function store(ConsumerRequest $request, Application $application, RequestEntityConsumer $action): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }
        $action->execute($application, $request->string('entity_id')->toString(), $request->string('reason')->trim()->toString(), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan terkirim ke Walidata.']);

        return to_route('admin.applications.show', $application);
    }

    public function index(Request $request): Response
    {
        $this->ensureApprover($request);

        return Inertia::render('admin/consumers/index', [
            'pending' => $this->queries->pending(),
            'decided' => $this->queries->decided(),
        ]);
    }

    public function decide(Request $request, string $entity, string $application, DecideEntityConsumer $action): RedirectResponse
    {
        $user = $this->ensureApprover($request);
        $request->validate([
            'decision' => ['required', 'in:approve,reject,revoke'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $decision = match ($request->string('decision')->toString()) {
            'approve' => 'approve',
            'reject' => 'reject',
            default => 'revoke',
        };
        $note = $request->filled('note') ? $request->string('note')->toString() : null;

        $action->execute($entity, $application, $decision, $note, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => match ($decision) {
            'approve' => 'Pengajuan disetujui.',
            'reject' => 'Pengajuan ditolak.',
            'revoke' => 'Persetujuan dicabut.',
        }]);

        return to_route('admin.consumers.index');
    }

    private function ensureApprover(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->access->hasAnywhere($user, PlatformPermission::ConsumerApprove)) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
