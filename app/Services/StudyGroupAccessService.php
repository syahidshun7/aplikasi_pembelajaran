<?php

namespace App\Services;

use App\Models\StudyGroup;
use App\Models\User;

class StudyGroupAccessService
{
    public const LEVEL_GATE_UNLOCK_CODES = [
        'DOOPLAB_LEVEL_GATE_UNLOCK',
        'STUDY_GROUP_LEVEL_GATE_UNLOCK',
    ];

    public function hasPaidLevelGatePass(?User $user, ?StudyGroup $group = null): bool
    {
        if (! $user) {
            return false;
        }

        $codes = self::LEVEL_GATE_UNLOCK_CODES;
        if ($group && (int) ($group->job_id ?? 0) > 0) {
            $codes[] = 'STUDY_GROUP_LEVEL_GATE_UNLOCK_JOB_'.(int) $group->job_id;
        }

        return $user->inventories()
            ->where('quantity', '>', 0)
            ->whereHas('item', fn ($query) => $query->whereIn('code', $codes))
            ->exists();
    }
}
