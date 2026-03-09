<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        $turnstileEnabled = (bool) config('services.turnstile.enabled');

        return Inertia::render('Auth/Register', [
            'jobs' => JobRole::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'emblem_path']),
            'turnstile' => [
                'enabled' => $turnstileEnabled,
                'site_key' => $turnstileEnabled ? config('services.turnstile.site_key') : null,
            ],
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $turnstileEnabled = (bool) config('services.turnstile.enabled');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'job_id' => 'required|exists:job_roles,id',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        if ($turnstileEnabled) {
            $rules['cf-turnstile-response'] = ['required', 'string'];
        }

        $request->validate($rules);

        if ($turnstileEnabled) {
            $this->verifyTurnstile($request->input('cf-turnstile-response'), $request->ip());
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'job_id' => $request->integer('job_id'),
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $defaultRoute = $user->isStaff() ? 'dashboard' : 'lobby';

        return redirect(route($defaultRoute, absolute: false));
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
}
