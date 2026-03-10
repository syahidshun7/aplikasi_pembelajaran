<?php

use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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

test('student cannot submit the same task bank quest twice', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'One Attempt Bank',
        'description' => 'Auto-check bank',
        'assessment_type' => 'multiple_choice',
        'is_active' => true,
    ]);

    $question = $taskBank->questions()->create([
        'question_text' => '1 + 1 = ?',
        'question_type' => 'multiple_choice',
        'options_json' => ['1', '2'],
        'answer_key' => '2',
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'One Attempt Quest',
        'description' => 'Only one submission allowed for task bank',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $first = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $question->uuid => '2',
        ],
    ]);
    $first->assertSessionHasNoErrors();

    $second = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $question->uuid => '2',
        ],
    ]);

    $second->assertSessionHasErrors('submission');
});

test('misconfigured multiple_choice task bank with essay questions can still be submitted and stays pending', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Broken MCQ Bank',
        'description' => 'Has essay but flagged as multiple_choice',
        'assessment_type' => 'multiple_choice',
        'is_active' => true,
    ]);

    $mcq = $taskBank->questions()->create([
        'question_text' => '1+1=?',
        'question_type' => 'multiple_choice',
        'options_json' => ['1', '2'],
        'answer_key' => '2',
        'weight' => 50,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $essay = $taskBank->questions()->create([
        'question_text' => 'Explain your reasoning.',
        'question_type' => 'essay',
        'options_json' => null,
        'answer_key' => null,
        'weight' => 50,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Broken MCQ Quest',
        'description' => 'Should not block submit',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'notes',
        'task_answers' => [
            $mcq->uuid => '2',
            $essay->uuid => 'Because 1+1 equals 2.',
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Pending');
    expect((int) $submission->grade)->toBe(0);
    expect($submission->scores_detail['assessment_type'] ?? null)->toBe('mixed');
    expect(($submission->scores_detail['auto_mcq']['earned_points'] ?? null))->toBe(50);
});

test('manual quest pending submission can be re-attempted (reupload file) until deadline', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Manual Quest',
        'description' => 'Allow resubmit pending',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $first = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'first report',
        'file' => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
    ]);
    $first->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Pending');
    expect($submission->file_path)->not->toBeNull();

    $oldPath = (string) $submission->file_path;
    Storage::disk('public')->assertExists($oldPath);

    // Re-attempt: reupload file only (keep content).
    $second = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'file' => UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
    ]);
    $second->assertSessionHasNoErrors();

    $submission->refresh();
    expect($submission->status)->toBe('Pending');
    expect((string) $submission->content)->toBe('first report');
    expect((string) $submission->file_path)->not->toBe('');
    expect((string) $submission->file_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists((string) $submission->file_path);
});

test('manual quest pending submission cannot be re-attempted after deadline', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Manual Quest Deadline',
        'description' => 'No update after deadline',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->subMinute(),
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'content' => 'initial',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'try update',
    ]);

    $response->assertSessionHasErrors('submission');
    $submission->refresh();
    expect((string) $submission->content)->toBe('initial');
});

test('manual quest submission cannot be re-attempted after it is approved', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Manual Quest Approved',
        'description' => 'No update after approved',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'content' => 'initial',
        'status' => 'Approved',
        'grade' => 80,
        'earned_exp' => 400,
        'earned_gold' => 400,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'try update',
    ]);

    $response->assertSessionHasErrors('submission');
    $submission->refresh();
    expect((string) $submission->content)->toBe('initial');
});
