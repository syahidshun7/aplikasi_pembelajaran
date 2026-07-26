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
            'study_group_staff' => \App\Http\Middleware\EnsureStudyGroupStaffAccess::class,
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

        $resolveErrorPayload = static function (int $status, \Throwable $e): array {
            $rawMessage = trim((string) $e->getMessage());
            $knownCodeMessageMap = [
                'STAFF_PLAY_MODE_QUEST_ACCESS_DENIED' => 'Mode staff aktif. Akses quest dinonaktifkan di mode preview.',
                'STAFF_PLAY_MODE_GUIDE_ACCESS_DENIED' => 'Mode staff aktif. Akses guide student dinonaktifkan di mode preview.',
                'EVENT_ACCESS_DENIED' => 'Kamu tidak memiliki akses ke event ini.',
                'EVENT_SELF_ATTENDANCE_DISABLED' => 'Absensi mandiri belum diaktifkan untuk event ini.',
                'CREATION_ACCESS_DENIED' => 'Kamu tidak memiliki akses ke karya ini.',
                'TASK_BANK_SUBMISSION_LOCKED' => 'Pengumpulan terkunci. Quest ini tidak menerima submission baru.',
                'SUBMISSION_ALREADY_PROCESSED' => 'Submission ini sudah diproses.',
                'SUBMISSION_DEADLINE_PASSED' => 'Batas waktu submission sudah lewat.',
                'MENTOR_JOB_REQUIRED' => 'Akun mentor kamu belum terhubung ke job role.',
                'MENTOR_CANNOT_MANAGE_QUEST_OUTSIDE_JOB' => 'Mentor hanya bisa mengelola quest sesuai job role masing-masing.',
                'MENTOR_CANNOT_ACCESS_TASK_BANK_OUTSIDE_JOB' => 'Mentor tidak bisa mengakses task bank di luar job role.',
                'MENTOR_CANNOT_ASSIGN_TASK_BANK_OUTSIDE_JOB' => 'Mentor tidak bisa assign task bank di luar job role.',
                'MENTOR_CANNOT_ACCESS_EVENT_OUTSIDE_JOB' => 'Mentor tidak bisa mengelola event di luar job role.',
                'MENTOR_EVENT_MUST_HAVE_STUDY_GROUP' => 'Event mentor wajib terhubung ke study group.',
                'MENTOR_CANNOT_MANAGE_EVENT_OUTSIDE_JOB' => 'Mentor tidak bisa mengelola event di luar job role.',
            ];

            $defaultMessage = match ($status) {
                400 => 'Permintaan tidak dapat diproses. Pastikan data yang dikirim sudah lengkap dan valid.',
                401 => 'Sesi login tidak valid. Silakan login ulang.',
                403 => 'Kamu tidak memiliki izin untuk membuka halaman ini.',
                404 => 'Halaman yang kamu cari tidak ditemukan atau sudah dipindahkan.',
                419 => 'Sesi kamu sudah kedaluwarsa. Silakan refresh halaman lalu coba lagi.',
                429 => 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
                default => 'Terjadi gangguan di server. Tim kami sudah dipanggil untuk memperbaiki kerusakan ini.',
            };

            $isInternalCode = $rawMessage !== '' && preg_match('/^[A-Z0-9_]+$/', $rawMessage) === 1;

            return [
                'status' => $status,
                'title' => match ($status) {
                    400 => 'INVALID_SIGNAL',
                    401 => 'AUTH_REQUIRED',
                    403 => 'ACCESS_DENIED',
                    404 => 'NOT_FOUND',
                    419 => 'SESSION_EXPIRED',
                    429 => 'RATE_LIMITED',
                    default => 'SYSTEM_FAILURE',
                },
                'message' => $knownCodeMessageMap[$rawMessage]
                    ?? ($isInternalCode ? $defaultMessage : ($rawMessage !== '' ? $rawMessage : $defaultMessage)),
            ];
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

        $exceptions->render(function (\Throwable $e, Request $request) use ($resolveStatusCode, $resolveErrorPayload) {
            // Tangani hanya permintaan HTML (bukan JSON/API)
            if ($request->expectsJson() || $request->wantsJson()) {
                return null;
            }

            $status = $resolveStatusCode($e);

            if (! in_array($status, [400, 401, 403, 404, 419, 429, 500], true)) {
                return null;
            }

            // Saat debug aktif, tetap pakai halaman custom untuk 4xx/429 agar user tidak melihat kode internal mentah.
            // Untuk 500 ke atas, biarkan Whoops/debug page default Laravel.
            if (config('app.debug') && $status >= 500) {
                return null;
            }

            $payload = $resolveErrorPayload($status, $e);

            return Inertia::render('Error', $payload)
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
