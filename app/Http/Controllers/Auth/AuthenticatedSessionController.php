<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\DailyQuest;
use App\Models\DailyQuestDefinition;
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

        $user = $request->user();
        $loginDailyQuest = DailyQuest::query()
            ->where('user_id', (int) ($user?->id ?? 0))
            ->whereDate('quest_date', now(config('app.timezone'))->toDateString())
            ->where('activity_type', DailyQuestDefinition::ACTIVITY_LOGIN)
            ->where('status', DailyQuest::STATUS_COMPLETED)
            ->whereNull('claimed_at')
            ->first();

        if ($loginDailyQuest) {
            $request->session()->flash('daily_quest_feedback', [
                'kind' => 'completed',
                'title' => 'DAILY QUEST COMPLETED!',
                'text' => sprintf(
                    '%s selesai. Reward +%d EXP / +%d GOLD siap diklaim.',
                    (string) $loginDailyQuest->title,
                    (int) ($loginDailyQuest->reward_exp ?? 0),
                    (int) ($loginDailyQuest->reward_gold ?? 0),
                ),
                'claimable_count' => 1,
                'quests' => [[
                    'id' => (int) $loginDailyQuest->id,
                    'uuid' => (string) $loginDailyQuest->uuid,
                    'title' => (string) $loginDailyQuest->title,
                    'status' => (string) $loginDailyQuest->status,
                    'progress' => (int) ($loginDailyQuest->progress_value ?? 0),
                    'target' => (int) ($loginDailyQuest->target_value ?? 1),
                    'reward_exp' => (int) ($loginDailyQuest->reward_exp ?? 0),
                    'reward_gold' => (int) ($loginDailyQuest->reward_gold ?? 0),
                ]],
            ]);
        }

        if ($user?->isStaff()) {
            return redirect()->route('dashboard');
        }

        return redirect()->intended(route('lobby', absolute: false));
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
