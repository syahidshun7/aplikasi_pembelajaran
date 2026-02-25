<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Models\User;
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
    protected $description = 'Sinkronisasi users.exp dan users.gold berdasarkan total submissions Approved.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $levelColumn = Schema::hasColumn('users', 'lvl') ? 'lvl' : (Schema::hasColumn('users', 'level') ? 'level' : null);

        $approvedSums = Submission::query()
            ->where('status', 'Approved')
            ->selectRaw('user_id, COALESCE(SUM(earned_exp), 0) AS exp_sum, COALESCE(SUM(earned_gold), 0) AS gold_sum')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $rows = [];
        $changedCount = 0;

        User::query()->orderBy('id')->chunkById(200, function ($users) use ($approvedSums, $isDryRun, $levelColumn, &$rows, &$changedCount): void {
            foreach ($users as $user) {
                $sum = $approvedSums->get($user->id);
                $newExp = (int) ($sum->exp_sum ?? 0);
                $newGold = (int) ($sum->gold_sum ?? 0);

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

                $user->update($updateData);
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
