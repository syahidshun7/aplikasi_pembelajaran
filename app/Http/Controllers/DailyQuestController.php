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
        $claimedQuest = $dailyQuestService->claim($dailyQuest, $request->user());

        $request->session()->flash('daily_quest_feedback', [
            'kind' => 'claimed',
            'title' => 'REWARD CLAIMED',
            'text' => sprintf(
                '%s claimed. +%d EXP / +%d GOLD tercatat sebagai bonus harian.',
                (string) $claimedQuest->title,
                (int) ($claimedQuest->reward_exp ?? 0),
                (int) ($claimedQuest->reward_gold ?? 0),
            ),
            'claimable_count' => 0,
            'quests' => [[
                'id' => (int) $claimedQuest->id,
                'uuid' => (string) $claimedQuest->uuid,
                'title' => (string) $claimedQuest->title,
                'status' => (string) $claimedQuest->status,
                'progress' => (int) ($claimedQuest->progress_value ?? 0),
                'target' => (int) ($claimedQuest->target_value ?? 1),
                'reward_exp' => (int) ($claimedQuest->reward_exp ?? 0),
                'reward_gold' => (int) ($claimedQuest->reward_gold ?? 0),
            ]],
        ]);

        return back()->with('message', 'DAILY_QUEST_REWARD_CLAIMED');
    }
}
