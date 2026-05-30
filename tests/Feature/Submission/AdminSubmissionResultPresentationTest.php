<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;

test('mentor result presentation trigger builds mentor dashboard output and stores stage nine json output', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Result Presentation Quest',
        'description' => 'Present validated evaluation result',
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
            'score' => 85,
            'criteria_scores' => [
                ['name' => 'Definisi', 'score' => 60, 'reason' => 'Konsep tepat.'],
                ['name' => 'Kelengkapan', 'score' => 25, 'reason' => 'Cukup lengkap.'],
            ],
            'strengths' => ['Konsep utama sudah sesuai'],
            'weaknesses' => ['Tambahkan detail contoh'],
            'feedback' => 'Jawaban sudah baik dan relevan.',
            'evaluation_confidence' => 0.91,
        ],
    ];

    $postEvaluationItems = [
        [
            'question_number' => 1,
            'question' => 'Apa itu internet?',
            'student_answer' => 'Internet adalah jaringan global untuk komunikasi data.',
            'reference_answer' => 'Internet adalah jaringan komputer global yang saling terhubung.',
            'subject' => 'technology',
            'question_type' => 'definition',
            'score' => 85,
            'final_score' => 85,
            'normalized_score' => 85,
            'confidence' => 0.91,
            'retry_count' => 0,
            'requires_manual_review' => false,
            'final_feedback' => 'Jawaban sudah benar namun masih perlu detail tambahan.',
            'validation_status' => 'success',
        ],
    ];

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'ready for result presentation',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
        'preprocess_started' => true,
        'semantic_items' => [
            [
                'complexity' => 'low',
                'tags' => ['technology', 'internet', 'definition'],
            ],
        ],
        'ai_evaluation_items' => $aiEvaluationItems,
        'ai_evaluation_result' => [
            'submission_id' => 'SUB-RESULT-PRES-FEATURE',
            'items' => $aiEvaluationItems,
            'warnings' => [],
            'ai_evaluation_status' => 'success',
            'next_stage' => 'evaluation_quality_review',
        ],
        'post_evaluation_validation_items' => $postEvaluationItems,
        'post_evaluation_validation_result' => [
            'submission_id' => 'SUB-RESULT-PRES-FEATURE',
            'items' => $postEvaluationItems,
            'warnings' => [],
            'post_evaluation_validation_status' => 'success',
            'next_stage' => 'result_finalization',
        ],
        'post_evaluation_validated_at' => now(),
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startResultPresentation', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('submission_id', $submission->submission_id);
    $response->assertJsonPath('result_presentation_status', 'success');
    $response->assertJsonPath('next_stage', 'mentor_verdict');
    $response->assertJsonPath('items.0.question_number', 1);
    $response->assertJsonPath('items.0.submission_status', 'evaluated');

    $submission->refresh();
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_EVALUATED);
    expect($submission->result_presentation_items)->toHaveCount(1);
    expect($submission->result_presentation_result['next_stage'] ?? null)->toBe('mentor_verdict');
    expect($submission->result_presented_at)->not->toBeNull();
});

test('mentor result presentation trigger requires post evaluation validation result first', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Result Presentation Guard Quest',
        'description' => 'Guard stage nine trigger',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'post evaluation validation missing',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
        'preprocess_started' => true,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startResultPresentation', ['submission' => $submission->uuid]));

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'POST_EVALUATION_VALIDATION_RESULT_REQUIRED');
});
