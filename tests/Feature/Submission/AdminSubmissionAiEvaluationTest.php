<?php

use App\Models\Quest;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\Submission;
use App\Models\User;

test('mentor ai evaluation trigger scores rubric-ready answers and stores stage seven json output', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $rubric = Rubric::query()->create([
        'title' => 'Rubric Evaluation Basic',
        'description' => 'Rubric for answer evaluation',
        'mentor_id' => $admin->id,
        'max_score' => 100,
    ]);

    RubricCriterion::query()->create([
        'rubric_id' => $rubric->id,
        'name' => 'Definisi',
        'weight' => 70,
        'order' => 1,
    ]);

    RubricCriterion::query()->create([
        'rubric_id' => $rubric->id,
        'name' => 'Kelengkapan',
        'weight' => 30,
        'order' => 2,
    ]);

    $quest = Quest::query()->create([
        'title' => 'AI Evaluation Quest',
        'description' => 'Evaluate only',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'rubric_id' => $rubric->id,
    ]);

    $rubricPreparedItems = [
        [
            'question_number' => 1,
            'question' => 'Apa itu internet?',
            'student_answer' => 'Internet adalah jaringan global untuk pertukaran informasi.',
            'reference_answer' => 'Internet adalah jaringan komputer global yang saling terhubung.',
            'subject' => 'technology',
            'question_type' => 'definition',
            'evaluation_strategy' => 'semantic_similarity',
            'selected_rubric' => [
                'rubric_id' => $rubric->id,
                'criteria' => [
                    ['name' => 'Definisi', 'weight' => 70],
                    ['name' => 'Kelengkapan', 'weight' => 30],
                ],
            ],
            'constraints' => [
                'score_range' => [0, 100],
                'allowed_feedback_length' => 200,
                'strict_json_output' => true,
            ],
            'evaluation_payload' => [
                'question' => 'Apa itu internet?',
            ],
            'payload_status' => 'ready',
        ],
    ];

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'rubric payload ready for ai evaluation',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
        'preprocess_started' => true,
        'rubric_preparation_items' => $rubricPreparedItems,
        'rubric_preparation_result' => [
            'submission_id' => 'SUB-EVAL-FEATURE',
            'items' => $rubricPreparedItems,
            'warnings' => [],
            'rubric_preparation_status' => 'success',
            'next_stage' => 'ai_evaluation',
        ],
        'rubric_prepared_at' => now(),
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startAiEvaluation', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('submission_id', $submission->submission_id);
    $response->assertJsonPath('ai_evaluation_status', 'success');
    $response->assertJsonPath('next_stage', 'evaluation_quality_review');
    $response->assertJsonPath('items.0.question_number', 1);
    $firstScore = (int) $response->json('items.0.score');
    expect($firstScore)->toBeGreaterThanOrEqual(0);
    expect($firstScore)->toBeLessThanOrEqual(100);

    $submission->refresh();
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_AI_CHECKED);
    expect($submission->ai_evaluation_items)->toHaveCount(1);
    expect($submission->ai_evaluation_result['next_stage'] ?? null)->toBe('evaluation_quality_review');
    expect($submission->ai_evaluated_at)->not->toBeNull();
});

test('mentor ai evaluation trigger requires rubric preparation result first', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'AI Evaluation Guard Quest',
        'description' => 'Guard ai evaluation',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'not rubric-ready yet',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
        'preprocess_started' => true,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startAiEvaluation', ['submission' => $submission->uuid]));

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'SUBMISSION_NOT_READY_FOR_AI_EVALUATION');
});
