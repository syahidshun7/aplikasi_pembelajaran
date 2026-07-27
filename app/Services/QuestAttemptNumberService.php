<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\UserQuestAttemptUnlock;

class QuestAttemptNumberService
{
    public function nextForSubmission(int $questId, int $userId): int
    {
        $unusedUnlock = UserQuestAttemptUnlock::query()
            ->where('quest_id', $questId)
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->orderBy('attempt_number')
            ->value('attempt_number');

        if ($unusedUnlock !== null) {
            return (int) $unusedUnlock;
        }

        return $this->nextHistoricalNumber($questId, $userId);
    }

    public function nextHistoricalNumber(int $questId, int $userId): int
    {
        $submissionMax = (int) Submission::withTrashed()
            ->where('quest_id', $questId)
            ->where('user_id', $userId)
            ->max('attempt_number');
        $unlockMax = (int) UserQuestAttemptUnlock::query()
            ->where('quest_id', $questId)
            ->where('user_id', $userId)
            ->max('attempt_number');

        return max($submissionMax, $unlockMax) + 1;
    }
}
