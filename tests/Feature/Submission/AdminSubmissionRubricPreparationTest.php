<?php

use App\Models\Quest;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\Submission;
use App\Models\User;

test('mentor rubric preparation trigger builds stage six payload and stores json output', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $rubric = Rubric::query()->create([
        'title' => 'Rubric Definition Basic',
        'description' => 'Rubric for definition questions',
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
        'title' => 'Rubric Prep Quest',
        'description' => 'Prepare rubric payload only',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'rubric_id' => $rubric->id,
    ]);

    $semanticItems = [
        [
            'question_number' => 1,
            'question' => 'Apa itu internet?',
            'answer' => 'Internet adalah jaringan global.',
            'language' => 'id',
            'subject' => 'technology',
            'question_type' => 'definition',
            'complexity' => 'low',
            'answer_length' => 'short',
            'answer_quality' => 'normal',
            'evaluation_strategy' => 'semantic_similarity',
            'tags' => ['technology', 'network'],
            'confidence' => 0.92,
        ],
    ];

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'semantic source ready for rubric preparation',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
        'preprocess_started' => true,
        'semantic_items' => $semanticItems,
        'semantic_result' => [
            'submission_id' => 'SUB-RUB-FEATURE',
            'items' => $semanticItems,
            'warnings' => [],
            'semantic_enrichment_status' => 'success',
            'next_stage' => 'rubric_preparation',
        ],
        'semantic_enriched_at' => now(),
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startRubricPreparation', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('submission_id', $submission->submission_id);
    $response->assertJsonPath('rubric_preparation_status', 'success');
    $response->assertJsonPath('next_stage', 'ai_evaluation');
    $response->assertJsonPath('items.0.payload_status', 'ready');
    $response->assertJsonPath('items.0.selected_rubric.rubric_id', $rubric->id);

    $submission->refresh();
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_RUBRIC_PREPARED);
    expect($submission->rubric_preparation_items)->toHaveCount(1);
    expect($submission->rubric_preparation_result['next_stage'] ?? null)->toBe('ai_evaluation');
    expect($submission->rubric_prepared_at)->not->toBeNull();
});

test('mentor rubric preparation trigger requires semantic enrichment result first', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Rubric Prep Guard Quest',
        'description' => 'Guard rubric preparation',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'not semantic yet',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_STRUCTURED,
        'preprocess_started' => true,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startRubricPreparation', ['submission' => $submission->uuid]));

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'SUBMISSION_NOT_READY_FOR_RUBRIC_PREPARATION');
});
