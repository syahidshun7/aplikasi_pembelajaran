<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\User;

test('admin can grade essay task bank using 0-100 per question and final score is averaged', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Essay Auto Grade Bank',
        'description' => 'Essay grading in percent scale',
        'assessment_type' => 'essay',
        'is_active' => true,
    ]);

    $questionA = $taskBank->questions()->create([
        'question_text' => 'Jelaskan apa itu internet.',
        'question_type' => 'essay',
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $questionB = $taskBank->questions()->create([
        'question_text' => 'Jelaskan manfaat AI untuk pendidikan.',
        'question_type' => 'essay',
        'weight' => 1,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Essay Quest',
        'description' => 'Task bank essay quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Raw submission payload',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
        'scores_detail' => [
            'source' => 'raw_task_bank_submission',
            'assessment_type' => 'essay',
            'answers' => [
                $questionA->uuid => 'Internet adalah jaringan global.',
                $questionB->uuid => 'AI membantu personalisasi pembelajaran.',
            ],
        ],
    ]);

    $response = $this->actingAs($admin)->post(route('admin.submissions.verdict', [
        'submission' => $submission->uuid,
    ]), [
        'status' => 'Approved',
        'feedback' => 'Good progress',
        'question_points' => [
            $questionA->uuid => 80,
            $questionB->uuid => 60,
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission->refresh();

    expect((int) $submission->grade)->toBe(70);
    expect((int) $submission->earned_gold)->toBe(700);
    expect((int) $submission->earned_exp)->toBe(700);
    expect(data_get($submission->scores_detail, 'verdict.source'))->toBe('essay');
    expect((float) data_get($submission->scores_detail, "verdict.task_bank.essay.by_question.{$questionA->uuid}.score_percent"))->toBe(80.0);
    expect((float) data_get($submission->scores_detail, "verdict.task_bank.essay.by_question.{$questionB->uuid}.score_percent"))->toBe(60.0);
});

test('admin verdict rejects essay score above 100 for task bank', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Essay Validation Bank',
        'description' => 'Validation check',
        'assessment_type' => 'essay',
        'is_active' => true,
    ]);

    $question = $taskBank->questions()->create([
        'question_text' => 'Apa itu machine learning?',
        'question_type' => 'essay',
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Essay Validation Quest',
        'description' => 'Task bank essay validation quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Raw submission payload',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
        'scores_detail' => [
            'source' => 'raw_task_bank_submission',
            'assessment_type' => 'essay',
            'answers' => [
                $question->uuid => 'Machine learning adalah cabang AI.',
            ],
        ],
    ]);

    $response = $this->actingAs($admin)->from('/admin/submissions')->post(route('admin.submissions.verdict', [
        'submission' => $submission->uuid,
    ]), [
        'status' => 'Approved',
        'feedback' => 'Check failed',
        'question_points' => [
            $question->uuid => 120,
        ],
    ]);

    $response->assertSessionHasErrors([
        "question_points.{$question->uuid}" => 'Skor essay harus 0-100.',
    ]);
});

