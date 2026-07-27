<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\Submission;
use Illuminate\Support\Collection;

class QuestEffectiveSubmissionService
{
    public function select(Collection $attempts, string $strategy): ?Submission
    {
        $graded = $attempts->filter(
            fn ($attempt) => in_array((string) $attempt->status, [
                Submission::STATUS_APPROVED,
                Submission::STATUS_REJECTED,
            ], true)
        );

        return match ($strategy) {
            Quest::GRADE_FIRST => $graded->sortBy([
                ['attempt_number', 'asc'],
                ['id', 'asc'],
            ])->first(),
            Quest::GRADE_LATEST => $graded->sortBy([
                ['attempt_number', 'desc'],
                ['id', 'desc'],
            ])->first(),
            default => $graded->sortBy([
                ['grade', 'desc'],
                ['attempt_number', 'desc'],
                ['id', 'desc'],
            ])->first(),
        };
    }
}
