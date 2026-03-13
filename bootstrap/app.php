<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            // Biarkan error bawaan Laravel (Whoops) saat debug aktif.
            if (config('app.debug')) {
                return null;
            }

            // Tangani hanya permintaan HTML (bukan JSON/API)
            if ($request->expectsJson() || $request->wantsJson()) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            if (! in_array($status, [400, 404, 500], true)) {
                return null;
            }

            $payload = [
                'status' => $status,
                'title' => match ($status) {
                    400 => 'INVALID_SIGNAL',
                    404 => 'NOT_FOUND',
                    default => 'SYSTEM_FAILURE',
                },
                'message' => match ($status) {
                    400 => 'Permintaan tidak dapat diproses. Pastikan data yang dikirim sudah lengkap dan valid.',
                    404 => 'Halaman yang kamu cari tidak ditemukan atau sudah dipindahkan.',
                    default => 'Terjadi gangguan di server. Tim kami sudah dipanggil untuk memperbaiki kerusakan ini.',
                },
            ];

            return Inertia::render('Error', $payload)
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
