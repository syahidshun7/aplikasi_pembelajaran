<?php

use App\Models\Submission;
use App\Services\UserRewardSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $userIds = Submission::query()
            ->join('user_quest_attempt_unlocks as unlocks', function ($join) {
                $join->on('unlocks.user_id', '=', 'submissions.user_id')
                    ->on('unlocks.quest_id', '=', 'submissions.quest_id')
                    ->on('unlocks.attempt_number', '=', 'submissions.attempt_number');
            })
            ->where('submissions.reward_eligible', false)
            ->distinct()
            ->pluck('submissions.user_id');

        Submission::query()
            ->join('user_quest_attempt_unlocks as unlocks', function ($join) {
                $join->on('unlocks.user_id', '=', 'submissions.user_id')
                    ->on('unlocks.quest_id', '=', 'submissions.quest_id')
                    ->on('unlocks.attempt_number', '=', 'submissions.attempt_number');
            })
            ->join('quests', 'quests.id', '=', 'submissions.quest_id')
            ->where('submissions.reward_eligible', false)
            ->select([
                'submissions.id',
                'submissions.grade',
                'quests.reward_gold',
                'quests.reward_exp',
            ])
            ->orderBy('submissions.id')
            ->each(function ($row) {
                $gradePortion = max(0, min(100, (int) $row->grade)) / 100;
                $goldReward = max(0, (int) $row->reward_gold);
                $expReward = (int) $row->reward_exp > 0
                    ? (int) $row->reward_exp
                    : $goldReward;

                Submission::query()->whereKey($row->id)->update([
                    'reward_eligible' => true,
                    'earned_gold' => (int) floor($goldReward * $gradePortion),
                    'earned_exp' => (int) floor($expReward * $gradePortion),
                ]);
            });

        $userIds->each(fn ($userId) => app(UserRewardSyncService::class)->sync((int) $userId));
    }

    public function down(): void
    {
        Submission::query()
            ->join('user_quest_attempt_unlocks as unlocks', function ($join) {
                $join->on('unlocks.user_id', '=', 'submissions.user_id')
                    ->on('unlocks.quest_id', '=', 'submissions.quest_id')
                    ->on('unlocks.attempt_number', '=', 'submissions.attempt_number');
            })
            ->pluck('submissions.id')
            ->each(fn ($id) => Submission::query()->whereKey($id)->update([
                'reward_eligible' => false,
                'earned_gold' => 0,
                'earned_exp' => 0,
            ]));
    }
};
