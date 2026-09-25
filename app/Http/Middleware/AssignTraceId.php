<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Support\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan trace id per request. Header X-Request-Id dari proxy dipakai bila formatnya aman.
 */
final readonly class AssignTraceId
{
    public function __construct(private TraceContext $trace) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');

        if (is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $incoming) === 1) {
            $this->trace->set($incoming);
        }

        Log::shareContext(['trace_id' => $this->trace->id()]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $this->trace->id());

        return $response;
    }
}
