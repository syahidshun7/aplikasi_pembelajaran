<?php

namespace App\Http\Controllers;

use App\Models\DailyQuest;
use App\Services\DailyQuestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyQuestController extends Controller
{
    public function claim(Request $request, DailyQuest $dailyQuest, DailyQuestService $dailyQuestService): RedirectResponse
    {
        if ((bool) $request->user()?->isStaffPlayMode()) {
            return back()->withErrors([
                'daily_quest' => 'Staff play mode tidak bisa claim reward harian.',
            ]);
        }

        $dailyQuestService->claim($dailyQuest, $request->user());

        return back();
    }
}
