<?php

namespace App\Services;

use App\Models\DailyQuest;
use App\Models\ShopTransaction;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserGoldAdjustment;
use App\Models\UserGoldTransfer;
use Illuminate\Support\Facades\Schema;
use App\Services\LevelingService;

class UserRewardSyncService
{
    public function sync(int $userId): void
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        if ($user->isStaffPlayMode()) {
            return;
        }

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
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN type = 'purchase' THEN -ABS(gold_change)
                        WHEN type = 'consume_unlock' THEN 0
                        ELSE gold_change
                    END
                ),0) as gold_total
            ")
            ->first();

        $adminAdjustmentGold = Schema::hasTable('user_gold_adjustments')
            ? (int) UserGoldAdjustment::query()
                ->where('user_id', $userId)
                ->sum('gold_change')
            : 0;

        $transferGold = Schema::hasTable('user_gold_transfers')
            ? (int) UserGoldTransfer::query()
                ->where('status', UserGoldTransfer::STATUS_COMPLETED)
                ->where(function ($query) use ($userId) {
                    $query->where('sender_id', $userId)
                        ->orWhere('recipient_id', $userId);
                })
                ->selectRaw("
                    COALESCE(SUM(
                        CASE
                            WHEN sender_id = ? THEN -amount
                            WHEN recipient_id = ? THEN amount
                            ELSE 0
                        END
                    ),0) as gold_total
                ", [$userId, $userId])
                ->value('gold_total')
            : 0;

        $newExp = (int) ($submissionTotals->exp_total ?? 0) + (int) ($dailyTotals->exp_total ?? 0);
        $newGold = (int) ($submissionTotals->gold_total ?? 0)
            + (int) ($dailyTotals->gold_total ?? 0)
            + (int) ($shopTotals->gold_total ?? 0)
            + $adminAdjustmentGold
            + $transferGold;
        $newGold = max(0, $newGold);

        $updateData = [
            'exp' => $newExp,
            'gold' => $newGold,
        ];

        $calculatedLevel = LevelingService::levelFromExp($newExp);

        if (Schema::hasColumn('users', 'lvl')) {
            $updateData['lvl'] = $calculatedLevel;
        } elseif (Schema::hasColumn('users', 'level')) {
            $updateData['level'] = $calculatedLevel;
        }

        User::query()->whereKey($userId)->update($updateData);
    }
}
