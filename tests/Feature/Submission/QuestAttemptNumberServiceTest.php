<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserQuestAttemptUnlock;
use App\Services\QuestAttemptNumberService;

test('attempt number skips retained unlock history after submissions are permanently deleted', function () {
    $user = User::factory()->create();
    $quest = Quest::query()->create([
        'title' => 'Retake Number Regression',
        'difficulty' => 'C-Rank',
        'reward_gold' => 100,
        'reward_exp' => 100,
        'status' => Quest::STATUS_AVAILABLE,
        'attempt_mode' => Quest::ATTEMPT_LIMITED,
        'max_attempts' => 2,
    ]);

    UserQuestAttemptUnlock::query()->create([
        'user_id' => $user->id,
        'quest_id' => $quest->id,
        'attempt_number' => 5,
        'unlocked_at' => now()->subMinute(),
        'used_at' => now(),
    ]);

    expect(app(QuestAttemptNumberService::class)
        ->nextHistoricalNumber($quest->id, $user->id))->toBe(6);
});

test('unused retake unlock is reused by the next submission', function () {
    $user = User::factory()->create();
    $quest = Quest::query()->create([
        'title' => 'Pending Retake Unlock',
        'difficulty' => 'C-Rank',
        'reward_gold' => 100,
        'reward_exp' => 100,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'attempt_number' => 4,
        'content' => 'previous',
        'status' => Submission::STATUS_APPROVED,
    ]);
    UserQuestAttemptUnlock::query()->create([
        'user_id' => $user->id,
        'quest_id' => $quest->id,
        'attempt_number' => 5,
        'unlocked_at' => now(),
    ]);

    expect(app(QuestAttemptNumberService::class)
        ->nextForSubmission($quest->id, $user->id))->toBe(5);
});
