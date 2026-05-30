<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;

test('mentor post evaluation validation trigger validates ai result and stores stage eight json output', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Post Evaluation Validation Quest',
        'description' => 'Validate evaluation output',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $aiEvaluationItems = [
        [
            'question_number' => 1,
            'question' => 'Apa itu internet?',
            'student_answer' => 'Internet adalah jaringan global untuk komunikasi data.',
            'reference_answer' => 'Internet adalah jaringan komputer global yang saling terhubung.',
            'subject' => 'technology',
            'question_type' => 'definition',
            'evaluation_strategy' => 'semantic_similarity',
            'score' => 85,
            'criteria_scores' => [
                ['name' => 'Definisi', 'score' => 60, 'reason' => 'Konsep tepat.'],
                ['name' => 'Kelengkapan', 'score' => 25, 'reason' => 'Cukup lengkap.'],
            ],
            'feedback' => 'Jawaban sudah cukup benar dan terarah.',
            'evaluation_confidence' => 0.9,
        ],
    ];

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'ready for post evaluation validation',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_AI_CHECKED,
        'preprocess_started' => true,
        'rubric_preparation_items' => [
            [
                'constraints' => [
                    'score_range' => [0, 100],
                ],
                'selected_rubric' => [
                    'criteria' => [
                        ['name' => 'Definisi', 'weight' => 70],
                        ['name' => 'Kelengkapan', 'weight' => 30],
                    ],
                ],
            ],
        ],
        'ai_evaluation_items' => $aiEvaluationItems,
        'ai_evaluation_result' => [
            'submission_id' => 'SUB-POST-EVAL-FEATURE',
            'items' => $aiEvaluationItems,
            'warnings' => [],
            'ai_evaluation_status' => 'success',
            'next_stage' => 'evaluation_quality_review',
        ],
        'ai_evaluated_at' => now(),
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startPostEvaluationValidation', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('submission_id', $submission->submission_id);
    $response->assertJsonPath('post_evaluation_validation_status', 'success');
    $response->assertJsonPath('next_stage', 'result_finalization');
    $response->assertJsonPath('items.0.question_number', 1);
    $response->assertJsonPath('items.0.validated', true);

    $submission->refresh();
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED);
    expect($submission->post_evaluation_validation_items)->toHaveCount(1);
    expect($submission->post_evaluation_validation_result['next_stage'] ?? null)->toBe('result_finalization');
    expect($submission->post_evaluation_validated_at)->not->toBeNull();
});

test('mentor post evaluation validation requires ai evaluation result first', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Post Eval Guard Quest',
        'description' => 'Guard post eval validation',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'ai evaluation missing',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_AI_CHECKED,
        'preprocess_started' => true,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startPostEvaluationValidation', ['submission' => $submission->uuid]));

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'AI_EVALUATION_RESULT_REQUIRED');
});
