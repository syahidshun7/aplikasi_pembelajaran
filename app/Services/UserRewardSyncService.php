<?php

namespace App\Services;

use App\Models\DailyQuest;
use App\Models\ShopTransaction;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class UserRewardSyncService
{
    public function sync(int $userId): void
    {
        $submissionTotals = Submission::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['Approved', 'Rejected'])
            ->selectRaw('COALESCE(SUM(earned_exp),0) as exp_total, COALESCE(SUM(earned_gold),0) as gold_total')
            ->first();

        $dailyTotals = DailyQuest::query()
            ->where('user_id', $userId)
            ->where('status', DailyQuest::STATUS_CLAIMED)
            ->selectRaw('COALESCE(SUM(reward_exp),0) as exp_total, COALESCE(SUM(reward_gold),0) as gold_total')
            ->first();

        $shopTotals = ShopTransaction::query()
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('meta')
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.admin_cancelled_at') IS NULL");
            })
            ->selectRaw('COALESCE(SUM(gold_change),0) as gold_total')
            ->first();

        $newExp = (int) ($submissionTotals->exp_total ?? 0) + (int) ($dailyTotals->exp_total ?? 0);
        $newGold = (int) ($submissionTotals->gold_total ?? 0)
            + (int) ($dailyTotals->gold_total ?? 0)
            + (int) ($shopTotals->gold_total ?? 0);
        $newGold = max(0, $newGold);

        $updateData = [
            'exp' => $newExp,
            'gold' => $newGold,
        ];

        if (Schema::hasColumn('users', 'lvl')) {
            $updateData['lvl'] = (int) floor($newExp / 1000) + 1;
        } elseif (Schema::hasColumn('users', 'level')) {
            $updateData['level'] = (int) floor($newExp / 1000) + 1;
        }

        User::query()->whereKey($userId)->update($updateData);
    }
}
