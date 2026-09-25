<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Shared\Support\TraceContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;

final readonly class DatabaseAuditLogger implements AuditLogger
{
    public function __construct(
        private ConnectionInterface $db,
        private AuthFactory $auth,
        private TraceContext $trace,
        private ?Request $request = null,
    ) {}

    public function log(
        string $action,
        ?string $objectId = null,
        ?string $objectType = null,
        array $changes = [],
        array $context = [],
        ?string $actorId = null,
    ): void {
        $userId = $actorId ?? $this->auth->guard()->id();
        $actorId = is_string($userId) ? $userId : null;

        $this->db->table('audit_logs')->insert([
            'actor_type' => $actorId !== null ? 'user' : 'system',
            'actor_id' => $actorId,
            'action' => $action,
            'object_id' => $objectId,
            'object_type' => $objectType,
            'changes' => $changes === [] ? null : json_encode($changes, JSON_THROW_ON_ERROR),
            'context' => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR),
            'ip' => $this->request?->ip(),
            'user_agent' => $this->request !== null ? mb_substr((string) $this->request->userAgent(), 0, 500) : null,
            'trace_id' => $this->trace->id(),
        ]);
    }
}
