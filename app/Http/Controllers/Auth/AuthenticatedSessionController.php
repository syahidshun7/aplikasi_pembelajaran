<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        $turnstileEnabled = (bool) config('services.turnstile.enabled');

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'turnstile' => [
                'enabled' => $turnstileEnabled,
                'site_key' => $turnstileEnabled ? config('services.turnstile.site_key') : null,
            ],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $turnstileEnabled = (bool) config('services.turnstile.enabled');
        if ($turnstileEnabled) {
            $this->verifyTurnstile((string) $request->input('cf-turnstile-response'), (string) $request->ip());
        }

        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function verifyTurnstile(string $token, string $ip): void
    {
        $secret = (string) config('services.turnstile.secret_key');
        if ($secret === '') {
            throw ValidationException::withMessages([
                'captcha' => 'Turnstile secret key belum dikonfigurasi.',
            ]);
        }

        $response = Http::asForm()
            ->withOptions([
                'verify' => (bool) config('services.turnstile.verify_ssl', true),
            ])
            ->timeout(10)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);

        $isSuccess = (bool) data_get($response->json(), 'success', false);
        if (! $response->ok() || ! $isSuccess) {
            throw ValidationException::withMessages([
                'captcha' => 'Verifikasi CAPTCHA gagal. Coba lagi.',
            ]);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
