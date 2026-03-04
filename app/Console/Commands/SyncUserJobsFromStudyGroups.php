<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncUserJobsFromStudyGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:sync-jobs-from-groups
                            {--dry-run : Tampilkan perubahan tanpa update database}
                            {--only-null : Hanya sync user yang job_id masih NULL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi users.job_id berdasarkan membership study group, dengan deteksi konflik multi-job.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $onlyNull = (bool) $this->option('only-null');

        $rows = DB::table('users')
            ->join('group_user', 'users.id', '=', 'group_user.user_id')
            ->join('study_groups', 'study_groups.id', '=', 'group_user.study_group_id')
            ->selectRaw('
                users.id as user_id,
                users.name as name,
                users.job_id as current_job_id,
                COUNT(*) as groups_count,
                COUNT(DISTINCT study_groups.job_id) as distinct_job_count,
                MIN(study_groups.job_id) as target_job_id,
                GROUP_CONCAT(DISTINCT study_groups.job_id ORDER BY study_groups.job_id SEPARATOR ",") as group_job_ids,
                SUM(CASE WHEN study_groups.job_id IS NULL THEN 1 ELSE 0 END) as null_job_groups_count
            ')
            ->groupBy('users.id', 'users.name', 'users.job_id')
            ->orderBy('users.id')
            ->get();

        $report = [];
        $updated = 0;
        $unchanged = 0;
        $failed = 0;
        $skippedOnlyNull = 0;

        foreach ($rows as $row) {
            $currentJobId = $row->current_job_id !== null ? (int) $row->current_job_id : null;
            $targetJobId = $row->target_job_id !== null ? (int) $row->target_job_id : null;
            $distinctJobCount = (int) $row->distinct_job_count;
            $nullJobGroupsCount = (int) $row->null_job_groups_count;

            $status = 'NO_CHANGE';
            $reason = '-';
            $action = 'SKIP';

            if ($nullJobGroupsCount > 0) {
                $status = 'FAILED';
                $reason = 'GROUP_WITHOUT_JOB';
                $failed++;
            } elseif ($distinctJobCount > 1) {
                $status = 'FAILED';
                $reason = 'MULTI_JOB_CONFLICT';
                $failed++;
            } elseif ($targetJobId === null) {
                $status = 'FAILED';
                $reason = 'NO_TARGET_JOB';
                $failed++;
            } elseif ($onlyNull && $currentJobId !== null) {
                $status = 'SKIPPED';
                $reason = 'ONLY_NULL_OPTION';
                $skippedOnlyNull++;
            } elseif ($currentJobId === $targetJobId) {
                $status = 'NO_CHANGE';
                $reason = 'ALREADY_SYNCED';
                $unchanged++;
            } else {
                $status = 'UPDATED';
                $reason = 'SYNCED_FROM_GROUPS';
                $action = $isDryRun ? 'DRY_RUN' : 'UPDATE';

                if (! $isDryRun) {
                    User::query()->whereKey($row->user_id)->update(['job_id' => $targetJobId]);
                }

                $updated++;
            }

            $report[] = [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'current_job' => $currentJobId ?? 'NULL',
                'target_job' => $targetJobId ?? 'NULL',
                'group_jobs' => $row->group_job_ids ?? 'NULL',
                'groups' => (int) $row->groups_count,
                'status' => $status,
                'action' => $action,
                'reason' => $reason,
            ];
        }

        $this->table(
            ['user_id', 'name', 'current_job', 'target_job', 'group_jobs', 'groups', 'status', 'action', 'reason'],
            $report
        );

        $modeLabel = $isDryRun ? 'DRY RUN' : 'EXECUTED';
        $this->newLine();
        $this->info("Mode: {$modeLabel}");
        $this->line("Total user anggota group: {$rows->count()}");
        $this->line("Updated: {$updated}");
        $this->line("No change: {$unchanged}");
        $this->line("Skipped (--only-null): {$skippedOnlyNull}");
        $this->line("Failed (conflict/data issue): {$failed}");

        if ($isDryRun) {
            $this->warn('Tidak ada data yang diubah karena menggunakan --dry-run.');
        }

        return self::SUCCESS;
    }
}

