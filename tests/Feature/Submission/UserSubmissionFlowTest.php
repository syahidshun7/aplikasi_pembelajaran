<?php

use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\User;
use Illuminate\Support\Str;

test('multiple choice task bank submission auto-checks and syncs user rewards on first submit', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'MCQ Core Bank',
        'description' => 'Auto-check bank',
        'assessment_type' => 'multiple_choice',
        'is_active' => true,
    ]);

    $questionA = $taskBank->questions()->create([
        'question_text' => '2 + 2 = ?',
        'question_type' => 'multiple_choice',
        'options_json' => ['3', '4', '5'],
        'answer_key' => '4',
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $questionB = $taskBank->questions()->create([
        'question_text' => 'Capital of Indonesia?',
        'question_type' => 'multiple_choice',
        'options_json' => ['Bandung', 'Jakarta', 'Surabaya'],
        'answer_key' => 'Jakarta',
        'weight' => 1,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'MCQ Quest',
        'description' => 'Quest with auto-check',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $questionA->uuid => '4',
            $questionB->uuid => 'Jakarta',
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Approved');
    expect((int) $submission->grade)->toBe(100);
    expect((int) $submission->earned_gold)->toBe(1000);
    expect((int) $submission->earned_exp)->toBe(1000);

    $user->refresh();
    expect((int) $user->gold)->toBe(1000);
    expect((int) $user->exp)->toBe(1000);
});

test('user cannot submit quest from study group they do not belong to', function () {
    $allowedUser = User::factory()->create();
    $blockedUser = User::factory()->create();

    $group = StudyGroup::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Alpha Team',
        'description' => 'Private group',
        'invite_code' => 'INVITE-ALPHA-001',
        'max_members' => 5,
    ]);

    $group->users()->attach($allowedUser->id, ['role' => 'member']);

    $quest = Quest::query()->create([
        'title' => 'Private Group Quest',
        'description' => 'Only for Alpha Team',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'study_group_id' => $group->id,
    ]);

    $response = $this->actingAs($blockedUser)->post(route('submissions.store', $quest->uuid), [
        'content' => 'Attempt from unauthorized user',
    ]);

    $response->assertForbidden();

    $exists = Submission::query()
        ->where('quest_id', $quest->id)
        ->where('user_id', $blockedUser->id)
        ->exists();

    expect($exists)->toBeFalse();
});

test('mixed task bank requires answers for all questions and stays pending for manual review', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Mixed Bank',
        'description' => 'Contains MCQ + essay',
        'assessment_type' => 'mixed',
        'is_active' => true,
    ]);

    $mcq = $taskBank->questions()->create([
        'question_text' => 'Which one is backend framework?',
        'question_type' => 'multiple_choice',
        'options_json' => ['Laravel', 'Figma', 'Blender'],
        'answer_key' => 'Laravel',
        'weight' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $essay = $taskBank->questions()->create([
        'question_text' => 'Explain dependency injection briefly.',
        'question_type' => 'essay',
        'options_json' => null,
        'answer_key' => null,
        'weight' => 3,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Mixed Task Quest',
        'description' => 'Requires all answers',
        'difficulty' => 'B-Rank',
        'reward_gold' => 1200,
        'reward_exp' => 1200,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $invalid = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $mcq->uuid => 'Laravel',
        ],
    ]);

    $invalid->assertSessionHasErrors(["task_answers.{$essay->uuid}"]);

    $valid = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'Please review my essay answer.',
        'task_answers' => [
            $mcq->uuid => 'Laravel',
            $essay->uuid => 'Dependency injection is providing dependencies from outside.',
        ],
    ]);

    $valid->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Pending');
    expect((int) $submission->grade)->toBe(0);
    expect($submission->scores_detail['assessment_type'] ?? null)->toBe('mixed');
    expect($submission->scores_detail['answers'][$essay->uuid] ?? null)
        ->toBe('Dependency injection is providing dependencies from outside.');
});
