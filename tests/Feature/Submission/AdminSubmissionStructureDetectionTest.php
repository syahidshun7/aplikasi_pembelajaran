<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;

test('mentor structure trigger segments cleaned text and stores stage four json output', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Structure Quest',
        'description' => 'Detect structure only',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $cleanText = "Kerjakan semua soal berikut!\n1. Apa itu Internet?\nInternet adalah jaringan global.\n\n2. Jelaskan AI\n-";

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'cleaned source ready for structure detection',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_CLEANED,
        'preprocess_started' => true,
        'extracted_text' => $cleanText,
        'extraction_result' => [
            'submission_id' => 'SUB-STRUCT-FEATURE',
            'detected_content_type' => 'txt',
            'extraction_method' => 'txt_reader',
            'raw_text' => $cleanText,
            'page_count' => 1,
            'ocr_used' => false,
            'ocr_confidence' => null,
            'extraction_status' => 'success',
            'warnings' => [],
        ],
        'clean_text' => $cleanText,
        'cleaning_result' => [
            'submission_id' => 'SUB-STRUCT-FEATURE',
            'clean_text' => $cleanText,
            'language' => 'id',
            'cleaning_status' => 'success',
            'changes_summary' => [
                'noise_removed' => 0,
                'ocr_corrections' => 0,
                'line_break_fixed' => 0,
                'garbage_removed' => 0,
            ],
            'warnings' => [],
            'next_stage' => 'structure_detection',
        ],
        'cleaning_language' => 'id',
        'cleaned_at' => now(),
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startStructureDetection', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('submission_id', $submission->submission_id);
    $response->assertJsonPath('document_pattern', 'numbered_list');
    $response->assertJsonPath('structure_detection_status', 'success');
    $response->assertJsonPath('next_stage', 'semantic_enrichment');
    $response->assertJsonPath('items.0.question_number', 1);
    $response->assertJsonPath('items.0.question', 'Apa itu Internet?');
    $response->assertJsonPath('items.0.answer', 'Internet adalah jaringan global.');
    $response->assertJsonPath('items.1.answer_status', 'empty');

    $submission->refresh();
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_STRUCTURED);
    expect($submission->structured_items)->toHaveCount(2);
    expect($submission->structured_items[0]['question'])->toBe('Apa itu Internet?');
    expect($submission->structure_result['next_stage'] ?? null)->toBe('semantic_enrichment');
    expect($submission->structure_detected_at)->not->toBeNull();
});

test('mentor structure trigger requires successful cleaning first', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Structure Guard Quest',
        'description' => 'Guard structure detection',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'not cleaned yet',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_PREPROCESSED,
        'preprocess_started' => true,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startStructureDetection', ['submission' => $submission->uuid]));

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'SUBMISSION_NOT_READY_FOR_STRUCTURE_DETECTION');
});
