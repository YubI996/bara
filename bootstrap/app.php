<?php

declare(strict_types=1);

use App\Http\Middleware\AssignTraceId;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Shared\Support\TraceContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->prepend([AssignTraceId::class, SecurityHeaders::class]);

        $middleware->web(append: [
            HandleAppearance::class,
            EnforceIdleTimeout::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // UX-004: halaman error berbahasa Indonesia dengan langkah pemulihan, bukan HTML bawaan.
        // Mode debug tetap menampilkan stack trace untuk 500 agar developer bisa menelusuri.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            $status = $response->getStatusCode();

            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'Sesi halaman ini kedaluwarsa. Isian Anda masih ada; silakan kirim ulang.']);

                return back();
            }

            if (in_array($status, [403, 404, 429, 503], true) || ($status >= 500 && ! config()->boolean('app.debug'))) {
                return Inertia::render('errors/show', [
                    'status' => $status >= 500 && $status !== 503 ? 500 : $status,
                    'trace_id' => $status >= 500 ? app(TraceContext::class)->id() : null,
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
