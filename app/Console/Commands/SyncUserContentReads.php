<?php

namespace App\Console\Commands;

use App\Models\DoopNewsPost;
use App\Models\Event;
use App\Models\Guide;
use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\User;
use App\Models\UserContentRead;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncUserContentReads extends Command
{
    protected $signature = 'content-reads:sync
                            {--days=30 : Tandai item yang dibuat/publish dalam N hari terakhir}
                            {--all-history : Tandai semua item visible, abaikan batas --days}
                            {--user-id= : Sinkron hanya untuk satu user id}
                            {--dry-run : Hitung tanpa menulis ke database}';

    protected $description = 'Backfill user_content_reads untuk item visible agar konten lama tidak semua muncul sebagai NEW.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $allHistory = (bool) $this->option('all-history');
        $days = max(1, (int) $this->option('days'));
        $cutoff = $allHistory ? null : now()->subDays($days);
        $targetUserId = $this->option('user-id') !== null ? max(0, (int) $this->option('user-id')) : null;
        $seenAt = now();

        $usersQuery = User::query()
            ->select(['id', 'job_id'])
            ->whereNotIn('role', User::staffRoles())
            ->orderBy('id');

        if ($targetUserId !== null) {
            if ($targetUserId <= 0) {
                $this->error('Option --user-id harus berupa id user valid.');
                return self::FAILURE;
            }

            $usersQuery->whereKey($targetUserId);
        }

        $totalUsers = (clone $usersQuery)->count();
        if ($totalUsers <= 0) {
            $this->warn('Tidak ada user student/player yang cocok untuk disinkron.');
            return self::SUCCESS;
        }

        $this->info('Sync user_content_reads');
        $this->line('Mode: '.($isDryRun ? 'DRY RUN' : 'EXECUTE'));
        $this->line('Scope: '.($allHistory ? 'all visible history' : "visible item sejak {$cutoff->toDateTimeString()}"));
        $this->line("Users: {$totalUsers}");
        $this->newLine();

        $totals = [
            UserContentRead::TYPE_QUEST => 0,
            UserContentRead::TYPE_GUIDE => 0,
            UserContentRead::TYPE_EVENT => 0,
            UserContentRead::TYPE_STUDY_GROUP => 0,
            UserContentRead::TYPE_DOOP_NEWS => 0,
        ];

        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        $usersQuery->chunkById(100, function ($users) use (&$totals, $cutoff, $seenAt, $isDryRun, $bar) {
            foreach ($users as $user) {
                $userId = (int) $user->id;
                $jobId = $user->job_id !== null ? (int) $user->job_id : null;
                $groupIds = $this->userGroupIds($userId, $jobId);

                $contentMap = [
                    UserContentRead::TYPE_QUEST => $this->visibleQuestIds($groupIds, $cutoff),
                    UserContentRead::TYPE_GUIDE => $this->visibleGuideIds($groupIds, $cutoff),
                    UserContentRead::TYPE_EVENT => $this->visibleEventIds($groupIds, $jobId, $cutoff),
                    UserContentRead::TYPE_STUDY_GROUP => $this->visibleStudyGroupIds($jobId, $cutoff),
                    UserContentRead::TYPE_DOOP_NEWS => $this->visibleDoopNewsIds($cutoff),
                ];

                foreach ($contentMap as $contentType => $contentIds) {
                    $missingIds = $this->missingSeenIds($userId, $contentType, $contentIds);
                    $totals[$contentType] += count($missingIds);

                    if (! $isDryRun && ! empty($missingIds)) {
                        $this->insertSeenRows($userId, $contentType, $missingIds, $seenAt);
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['content_type', $isDryRun ? 'would_sync' : 'synced'],
            collect($totals)->map(fn ($count, $type) => [$type, $count])->values()->all()
        );

        $grandTotal = array_sum($totals);
        $this->info(($isDryRun ? 'Would sync' : 'Synced')." {$grandTotal} read marker(s).");

        if ($isDryRun) {
            $this->warn('Tidak ada data yang diubah karena memakai --dry-run.');
        }

        return self::SUCCESS;
    }

    private function userGroupIds(int $userId, ?int $jobId): array
    {
        return DB::table('group_user')
            ->join('study_groups', 'study_groups.id', '=', 'group_user.study_group_id')
            ->where('group_user.user_id', $userId)
            ->whereNull('group_user.deleted_at')
            ->whereNull('study_groups.deleted_at')
            ->when($jobId === null, fn ($query) => $query->whereNull('study_groups.job_id'))
            ->when($jobId !== null, fn ($query) => $query->where('study_groups.job_id', $jobId))
            ->pluck('study_groups.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function visibleQuestIds(array $groupIds, ?Carbon $cutoff): array
    {
        return Quest::query()
            ->where(function ($query) use ($groupIds) {
                $query->whereNull('study_group_id')
                    ->orWhereIn('study_group_id', $groupIds);
            })
            ->listedForUsers()
            ->when($cutoff, fn ($query) => $query->where('created_at', '>=', $cutoff))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function visibleGuideIds(array $groupIds, ?Carbon $cutoff): array
    {
        return Guide::query()
            ->where(function ($query) use ($groupIds) {
                $query->whereNull('study_group_id')
                    ->orWhereIn('study_group_id', $groupIds);
            })
            ->when($cutoff, fn ($query) => $query->where('created_at', '>=', $cutoff))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function visibleEventIds(array $groupIds, ?int $jobId, ?Carbon $cutoff): array
    {
        return Event::query()
            ->where(function ($query) use ($groupIds, $jobId) {
                $query->where(function ($publicQuery) use ($jobId) {
                    $publicQuery->whereNull('study_group_id')
                        ->where(function ($audienceQuery) use ($jobId) {
                            $audienceQuery->whereNull('job_id');

                            if ($jobId !== null) {
                                $audienceQuery->orWhere('job_id', $jobId);
                            }
                        });
                })
                    ->orWhereIn('study_group_id', $groupIds);
            })
            ->when($cutoff, fn ($query) => $query->where('created_at', '>=', $cutoff))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function visibleStudyGroupIds(?int $jobId, ?Carbon $cutoff): array
    {
        return StudyGroup::query()
            ->when($jobId === null, fn ($query) => $query->whereNull('job_id'))
            ->when($jobId !== null, fn ($query) => $query->where('job_id', $jobId))
            ->when($cutoff, fn ($query) => $query->where('created_at', '>=', $cutoff))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function visibleDoopNewsIds(?Carbon $cutoff): array
    {
        return DoopNewsPost::query()
            ->published()
            ->when($cutoff, fn ($query) => $query->where('published_at', '>=', $cutoff))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function missingSeenIds(int $userId, string $contentType, array $contentIds): array
    {
        if (empty($contentIds)) {
            return [];
        }

        $seenIdSet = UserContentRead::seenContentIds($userId, $contentType, $contentIds)
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        return collect($contentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && ! isset($seenIdSet[$id]))
            ->unique()
            ->values()
            ->all();
    }

    private function insertSeenRows(int $userId, string $contentType, array $contentIds, Carbon $seenAt): void
    {
        $now = now();
        $rows = collect($contentIds)
            ->map(fn ($contentId) => [
                'user_id' => $userId,
                'content_type' => $contentType,
                'content_id' => (int) $contentId,
                'seen_at' => $seenAt,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('user_content_reads')->insertOrIgnore($rows);
    }
}
