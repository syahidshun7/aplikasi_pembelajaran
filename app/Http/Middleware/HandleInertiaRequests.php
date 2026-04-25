<?php

namespace App\Http\Middleware;

use App\Services\DailyQuestService;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'email_verified_at' => $request->user()->email_verified_at,
                    'role' => $request->user()->role,
                    'staff_play_mode' => (bool) $request->user()->isStaffPlayMode(),
                    'is_paid_member' => (bool) $request->user()->isPaidMember(),
                    'can_access_dooplab' => (bool) $request->user()->canAccessDoopLab(),
                    'player_mode_label' => $request->user()->isStaffPlayMode() ? 'STAFF_PLAY_MODE' : 'PLAYER_MODE',
                    'player_mode_notice' => $request->user()->isStaffPlayMode()
                        ? 'Mode preview aktif: reward, leaderboard, dan akses kelas student tidak dihitung.'
                        : null,
                ] : null,
            ],
            'notificationCenter' => $request->user()
                ? fn () => NotificationPresenter::summary($request->user())
                : null,
            'dailyQuestCenter' => $request->user()
                ? fn () => app(DailyQuestService::class)->quickStatusForUser($request->user())
                : null,
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'dailyQuest' => fn () => $request->session()->get('daily_quest_feedback'),
            ],
        ];
    }
}
