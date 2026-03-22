<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\ErrorLog;
use App\Mail\ServerErrorAlert;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
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
        $resolveStatusCode = static function (\Throwable $e): int {
            return match (true) {
                $e instanceof ValidationException => $e->status,
                $e instanceof AuthenticationException => 401,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };
        };

        // Report hook: kirim alert email untuk 5xx di production (dengan throttling)
        $exceptions->report(function (\Throwable $e) use ($resolveStatusCode) {
            if (config('app.debug')) {
                return null;
            }

            $status = $resolveStatusCode($e);
            if ($status < 500) {
                return null;
            }

            $traceId = (string) Str::uuid();
            $request = request();

            try {
                $safePayload = $request ? $request->except([
                    'password',
                    'password_confirmation',
                    'current_password',
                ]) : [];

                $headers = $request ? collect($request->headers->all())
                    ->map(fn ($v) => is_array($v) ? array_slice($v, 0, 3) : $v)
                    ->take(25)
                    ->all() : [];

                ErrorLog::create([
                    'trace_id' => $traceId,
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => Str::limit((string) $e->getMessage(), 2000),
                    'status_code' => $status,
                    'url' => $request?->fullUrl(),
                    'method' => $request?->method(),
                    'user_id' => $request?->user()?->id,
                    'ip' => $request?->ip(),
                    'user_agent' => $request?->userAgent(),
                    'context' => [
                        'referer' => $request?->headers->get('referer'),
                        'payload' => $safePayload,
                        'headers' => $headers,
                    ],
                ]);
            } catch (\Throwable $logError) {
                // swallow logging errors to avoid masking the original exception
            }

            $adminEmail = config('mail.admin_alert');
            if (empty($adminEmail)) {
                return null;
            }

            $hash = sha1(get_class($e).$e->getMessage());
            $throttleKey = "alert:server-error:{$hash}";
            if (! Cache::add($throttleKey, 1, now()->addMinutes(10))) {
                return null; // sudah dikirim baru-baru ini
            }

            $requestUrl = $request?->fullUrl() ?? '-';

            Mail::to($adminEmail)->queue(new ServerErrorAlert($e, $requestUrl, $status, $traceId));

            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! ($request->expectsJson() || $request->wantsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! ($request->expectsJson() || $request->wantsJson())) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unauthenticated.',
            ], 401);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! ($request->expectsJson() || $request->wantsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'Data yang diminta tidak ditemukan.',
            ], 404);
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($resolveStatusCode) {
            // Biarkan error bawaan Laravel (Whoops) saat debug aktif.
            if (config('app.debug')) {
                return null;
            }

            // Tangani hanya permintaan HTML (bukan JSON/API)
            if ($request->expectsJson() || $request->wantsJson()) {
                return null;
            }

            $status = $resolveStatusCode($e);

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
