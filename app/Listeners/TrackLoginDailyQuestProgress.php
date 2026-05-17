<?php

namespace App\Listeners;

use App\Models\DailyQuestDefinition;
use App\Models\User;
use App\Services\DailyQuestService;
use Illuminate\Auth\Events\Login;

class TrackLoginDailyQuestProgress
{
    public function __construct(
        private readonly DailyQuestService $dailyQuestService,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->dailyQuestService->recordActivity(
            $event->user,
            DailyQuestDefinition::ACTIVITY_LOGIN,
            1,
            [
                'guard' => (string) $event->guard,
            ],
        );
    }
}
