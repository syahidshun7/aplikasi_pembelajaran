<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;

test('mentor preprocessing trigger extracts raw text and stores stage two json output', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Extraction Quest',
        'description' => 'Extract only',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => "1. Apa itu internet?\nInternet adalah jaringan global.",
        'status' => Submission::STATUS_PENDING,
        'pipeline_status' => Submission::PIPELINE_STATUS_PENDING_PREPROCESSING,
        'preprocess_started' => false,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.submissions.startPreprocessing', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJson([
        'submission_id' => $submission->submission_id,
        'detected_content_type' => 'txt',
        'extraction_method' => 'txt_reader',
        'raw_text' => "1. Apa itu internet?\nInternet adalah jaringan global.",
        'page_count' => 1,
        'ocr_used' => false,
        'ocr_confidence' => null,
        'extraction_status' => 'success',
        'warnings' => [],
    ]);

    $submission->refresh();
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PREPROCESSED);
    expect($submission->preprocess_started)->toBeTrue();
    expect($submission->extracted_text)->toBe("1. Apa itu internet?\nInternet adalah jaringan global.");
    expect($submission->extraction_result['extraction_status'] ?? null)->toBe('success');
    expect($submission->extracted_at)->not->toBeNull();
});
