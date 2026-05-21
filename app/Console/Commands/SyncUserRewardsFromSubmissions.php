<?php

namespace App\Console\Commands;

use App\Models\DailyQuest;
use App\Models\ShopTransaction;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserGoldAdjustment;
use App\Models\UserGoldTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncUserRewardsFromSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:sync-rewards {--dry-run : Tampilkan perubahan tanpa update database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi users.exp dan users.gold berdasarkan total submission, daily quest claim, dan transaksi shop yang masih valid.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $levelColumn = Schema::hasColumn('users', 'lvl') ? 'lvl' : (Schema::hasColumn('users', 'level') ? 'level' : null);

        $approvedSums = Submission::query()
            ->whereIn('status', ['Approved', 'Rejected'])
            ->selectRaw('user_id, COALESCE(SUM(earned_exp), 0) AS exp_sum, COALESCE(SUM(earned_gold), 0) AS gold_sum')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $dailyClaimedSums = DailyQuest::query()
            ->where('status', DailyQuest::STATUS_CLAIMED)
            ->selectRaw('user_id, COALESCE(SUM(reward_exp), 0) AS exp_sum, COALESCE(SUM(reward_gold), 0) AS gold_sum')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $shopGoldSums = ShopTransaction::query()
            ->where(function ($query) {
                $query->whereNull('meta')
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.admin_cancelled_at') IS NULL");
            })
            ->selectRaw("
                user_id,
                COALESCE(SUM(
                    CASE
                        WHEN type = 'purchase' THEN -ABS(gold_change)
                        WHEN type = 'consume_unlock' THEN 0
                        ELSE gold_change
                    END
                ), 0) AS gold_sum
            ")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $adminAdjustmentSums = Schema::hasTable('user_gold_adjustments')
            ? UserGoldAdjustment::query()
                ->selectRaw('user_id, COALESCE(SUM(gold_change), 0) AS gold_sum')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id')
            : collect();

        $transferSums = Schema::hasTable('user_gold_transfers')
            ? UserGoldTransfer::query()
                ->where('status', UserGoldTransfer::STATUS_COMPLETED)
                ->selectRaw('sender_id, recipient_id, amount')
                ->get()
                ->reduce(function ($carry, UserGoldTransfer $transfer) {
                    $senderId = (int) $transfer->sender_id;
                    $recipientId = (int) $transfer->recipient_id;
                    $amount = (int) $transfer->amount;

                    $carry[$senderId] = ($carry[$senderId] ?? 0) - $amount;
                    $carry[$recipientId] = ($carry[$recipientId] ?? 0) + $amount;

                    return $carry;
                }, [])
            : [];

        $rows = [];
        $changedCount = 0;

        User::query()->orderBy('id')->chunkById(200, function ($users) use ($approvedSums, $dailyClaimedSums, $shopGoldSums, $adminAdjustmentSums, $transferSums, $isDryRun, $levelColumn, &$rows, &$changedCount): void {
            foreach ($users as $user) {
                $submissionSum = $approvedSums->get($user->id);
                $dailySum = $dailyClaimedSums->get($user->id);
                $shopSum = $shopGoldSums->get($user->id);
                $adminAdjustmentSum = $adminAdjustmentSums->get($user->id);
                $newExp = (int) ($submissionSum->exp_sum ?? 0) + (int) ($dailySum->exp_sum ?? 0);
                $newGold = (int) ($submissionSum->gold_sum ?? 0)
                    + (int) ($dailySum->gold_sum ?? 0)
                    + (int) ($shopSum->gold_sum ?? 0)
                    + (int) ($adminAdjustmentSum->gold_sum ?? 0)
                    + (int) ($transferSums[(int) $user->id] ?? 0);
                $newGold = max(0, $newGold);

                $oldExp = (int) ($user->exp ?? 0);
                $oldGold = (int) ($user->gold ?? 0);

                $isChanged = ($oldExp !== $newExp) || ($oldGold !== $newGold);
                if ($isChanged) {
                    $changedCount++;
                }

                $rows[] = [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'old_exp' => $oldExp,
                    'new_exp' => $newExp,
                    'old_gold' => $oldGold,
                    'new_gold' => $newGold,
                    'changed' => $isChanged ? 'YES' : 'NO',
                ];

                if ($isDryRun || ! $isChanged) {
                    continue;
                }

                $updateData = [
                    'exp' => $newExp,
                    'gold' => $newGold,
                ];

                if ($levelColumn !== null) {
                    $updateData[$levelColumn] = (int) floor($newExp / 1000) + 1;
                }

                // Use query update to bypass fillable restrictions on User model.
                User::query()->whereKey($user->id)->update($updateData);
            }
        });

        $this->table(
            ['user_id', 'name', 'old_exp', 'new_exp', 'old_gold', 'new_gold', 'changed'],
            $rows
        );

        if ($isDryRun) {
            $this->warn("DRY RUN: {$changedCount} user akan diubah.");
            return self::SUCCESS;
        }

        $this->info("Selesai. {$changedCount} user berhasil disinkronkan.");
        return self::SUCCESS;
    }
}
