<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\UserQuestAttemptSession;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class TaskBankExamSessionService
{
    public function supports(Quest $quest): bool
    {
        $quest->loadMissing('taskBank');
        $type = (string) ($quest->taskBank?->assessment_type ?? '');

        return $quest->taskBank !== null
            && ! in_array($type, ['platforming', 'word_match'], true)
            && (bool) $quest->taskBank->has_time_limit;
    }

    public function resolve(Quest $quest, int $userId, int $attemptNumber): ?UserQuestAttemptSession
    {
        if (! $this->supports($quest)) {
            return null;
        }

        $durationMinutes = max(1, (int) ($quest->taskBank?->duration ?? 60));
        $now = now();

        try {
            $session = UserQuestAttemptSession::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'quest_id' => (int) $quest->id,
                    'attempt_number' => max(1, $attemptNumber),
                ],
                [
                    'submission_token' => (string) Str::uuid(),
                    'started_at' => $now,
                    'expires_at' => $now->copy()->addMinutes($durationMinutes),
                ],
            );
            if (! $session->submission_token) {
                $session->update(['submission_token' => (string) Str::uuid()]);
            }

            return $session;
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }

            $session = UserQuestAttemptSession::query()
                ->where('user_id', $userId)
                ->where('quest_id', (int) $quest->id)
                ->where('attempt_number', max(1, $attemptNumber))
                ->firstOrFail();
            if (! $session->submission_token) {
                $session->update(['submission_token' => (string) Str::uuid()]);
            }

            return $session;
        }
    }

    public function isExpired(?UserQuestAttemptSession $session): bool
    {
        return $session !== null && $session->expires_at->lessThanOrEqualTo(now());
    }

    public function markSubmitted(?UserQuestAttemptSession $session): void
    {
        if ($session && $session->submitted_at === null) {
            $session->update(['submitted_at' => now()]);
        }
    }
}
