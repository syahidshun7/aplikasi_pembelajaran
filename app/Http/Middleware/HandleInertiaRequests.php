<?php

namespace App\Http\Middleware;

use App\Services\DailyQuestService;
use App\Services\LevelingService;
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
        $authUser = $request->user();
        $levelProgress = $authUser
            ? LevelingService::progress((int) ($authUser->exp ?? 0))
            : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authUser ? [
                    'id' => $authUser->id,
                    'name' => $authUser->name,
                    'username' => $authUser->username,
                    'email' => $authUser->email,
                    'email_verified_at' => $authUser->email_verified_at,
                    'role' => $authUser->role,
                    'staff_play_mode' => (bool) $authUser->isStaffPlayMode(),
                    'is_paid_member' => (bool) $authUser->isPaidMember(),
                    'can_access_dooplab' => (bool) $authUser->canAccessDoopLab(),
                    'player_mode_label' => $authUser->isStaffPlayMode() ? 'STAFF_PLAY_MODE' : 'PLAYER_MODE',
                    'player_mode_notice' => $authUser->isStaffPlayMode()
                        ? 'Mode preview aktif: reward, leaderboard, dan akses kelas student tidak dihitung.'
                        : null,
                    'lvl' => $levelProgress['level'] ?? 1,
                    'exp' => (int) ($authUser->exp ?? 0),
                    'level_progress' => $levelProgress,
                ] : null,
            ],
            'notificationCenter' => $authUser
                ? fn () => NotificationPresenter::summary($authUser)
                : null,
            'dailyQuestCenter' => $authUser
                ? fn () => app(DailyQuestService::class)->quickStatusForUser($authUser)
                : null,
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'dailyQuest' => fn () => $request->session()->get('daily_quest_feedback'),
                'checkInCode' => fn () => $request->session()->get('check_in_code'),
            ],
        ];
    }
}
