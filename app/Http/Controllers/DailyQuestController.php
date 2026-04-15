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
        $dailyQuestService->claim($dailyQuest, $request->user());

        return back();
    }
}
