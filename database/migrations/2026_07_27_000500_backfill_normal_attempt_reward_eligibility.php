<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\UserRewardSyncService;

return new class extends Migration
{
    public function up(): void
    {
        $normalAttempts = DB::table('submissions')
            ->join('quests', 'quests.id', '=', 'submissions.quest_id')
            ->leftJoin('user_quest_attempt_unlocks', function ($join) {
                $join->on('user_quest_attempt_unlocks.user_id', '=', 'submissions.user_id')
                    ->on('user_quest_attempt_unlocks.quest_id', '=', 'submissions.quest_id')
                    ->on('user_quest_attempt_unlocks.attempt_number', '=', 'submissions.attempt_number');
            })
            ->where('submissions.reward_eligible', false)
            ->whereNull('user_quest_attempt_unlocks.id')
            ->whereNull('submissions.deleted_at')
            ->get([
                'submissions.id',
                'submissions.user_id',
                'submissions.grade',
                'submissions.status',
                'quests.reward_exp',
                'quests.reward_gold',
            ]);

        foreach ($normalAttempts as $submission) {
            $gradePortion = in_array((string) $submission->status, ['Approved', 'Rejected'], true)
                ? max(0, min(100, (int) ($submission->grade ?? 0))) / 100
                : 0;

            DB::table('submissions')
                ->where('id', $submission->id)
                ->update([
                    'reward_eligible' => true,
                    'earned_exp' => (int) floor((int) ($submission->reward_exp ?? 0) * $gradePortion),
                    'earned_gold' => (int) floor((int) ($submission->reward_gold ?? 0) * $gradePortion),
                    'updated_at' => now(),
                ]);
        }

        $normalAttempts
            ->pluck('user_id')
            ->unique()
            ->each(fn ($userId) => app(UserRewardSyncService::class)->sync((int) $userId));
    }

    public function down(): void
    {
        // Eligibility cannot be safely inferred back after normal attempts earn rewards.
    }
};
