<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;

test('mentor semantic enrichment trigger enriches structured items and stores stage five json output', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Semantic Quest',
        'description' => 'Semantic enrichment only',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $structuredItems = [
        [
            'question_number' => 1,
            'question' => 'Apa itu internet?',
            'answer' => 'Internet adalah jaringan global.',
            'answer_status' => 'filled',
            'is_empty' => false,
        ],
        [
            'question_number' => 2,
            'question' => 'Hitung 20 + 3',
            'answer' => '23',
            'answer_status' => 'filled',
            'is_empty' => false,
        ],
    ];

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'structured source ready for semantic enrichment',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_STRUCTURED,
        'preprocess_started' => true,
        'structured_items' => $structuredItems,
        'structure_result' => [
            'submission_id' => 'SUB-SEM-FEATURE',
            'document_pattern' => 'numbered_list',
            'items' => $structuredItems,
            'instruction_blocks' => [],
            'warnings' => [],
            'structure_detection_status' => 'success',
            'next_stage' => 'semantic_enrichment',
        ],
        'structure_detected_at' => now(),
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startSemanticEnrichment', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('submission_id', $submission->submission_id);
    $response->assertJsonPath('semantic_enrichment_status', 'success');
    $response->assertJsonPath('next_stage', 'rubric_preparation');
    $response->assertJsonPath('items.0.question_type', 'definition');
    $response->assertJsonPath('items.1.question_type', 'calculation');
    $response->assertJsonPath('items.1.evaluation_strategy', 'rule_based_evaluation');
    $response->assertJsonStructure([
        'items' => [
            [
                'difficulty',
                'expected_concepts',
                'semantic_tags',
            ],
        ],
    ]);

    $submission->refresh();
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED);
    expect($submission->semantic_items)->toHaveCount(2);
    expect($submission->semantic_items[0]['evaluation_strategy'])->toBe('semantic_similarity');
    expect($submission->semantic_result['next_stage'] ?? null)->toBe('rubric_preparation');
    expect($submission->semantic_enriched_at)->not->toBeNull();
});

test('mentor semantic enrichment trigger requires structure detection result first', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Semantic Guard Quest',
        'description' => 'Guard semantic enrichment',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'not structured yet',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_CLEANED,
        'preprocess_started' => true,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startSemanticEnrichment', ['submission' => $submission->uuid]));

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'SUBMISSION_NOT_READY_FOR_SEMANTIC_ENRICHMENT');
});
