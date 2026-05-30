<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;

test('mentor cleaning trigger normalizes extracted text and stores stage three json output', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Cleaning Quest',
        'description' => 'Clean only',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'raw source already extracted',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_PREPROCESSED,
        'preprocess_started' => true,
        'extracted_text' => "Page 1 of 2\n1. Apa\titu\rlnternet?\nlnternet adalah\njaringan global.",
        'extraction_result' => [
            'submission_id' => 'SUB-CLEAN-FEATURE',
            'detected_content_type' => 'txt',
            'extraction_method' => 'txt_reader',
            'raw_text' => "Page 1 of 2\n1. Apa\titu\rlnternet?\nlnternet adalah\njaringan global.",
            'page_count' => 1,
            'ocr_used' => false,
            'ocr_confidence' => null,
            'extraction_status' => 'success',
            'warnings' => [],
        ],
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startCleaning', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('submission_id', $submission->submission_id);
    $response->assertJsonPath('language', 'id');
    $response->assertJsonPath('cleaning_status', 'success');
    $response->assertJsonPath('next_stage', 'structure_detection');

    $submission->refresh();
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_CLEANED);
    expect($submission->clean_text)->toContain('1. Apa itu Internet?');
    expect($submission->clean_text)->toContain('Internet adalah jaringan global.');
    expect($submission->cleaning_language)->toBe('id');
    expect($submission->cleaning_result['next_stage'] ?? null)->toBe('structure_detection');
    expect($submission->cleaned_at)->not->toBeNull();
});

test('mentor cleaning trigger requires successful extraction first', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Cleaning Guard Quest',
        'description' => 'Guard cleaning',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'not extracted yet',
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_PENDING_PREPROCESSING,
        'preprocess_started' => false,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startCleaning', ['submission' => $submission->uuid]));

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'SUBMISSION_NOT_READY_FOR_CLEANING');
});
