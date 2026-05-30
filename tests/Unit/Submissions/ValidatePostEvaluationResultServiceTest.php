<?php

use App\Models\Submission;
use App\Services\Submissions\ValidatePostEvaluationResultService;

uses(Tests\TestCase::class);

test('post evaluation validation returns stable success result for consistent ai output', function () {
    $service = new ValidatePostEvaluationResultService();

    $submission = new Submission([
        'submission_id' => 'SUB-POST-EVAL-UNIT-001',
        'ai_evaluation_items' => [
            [
                'question_number' => 1,
                'question' => 'Apa itu internet?',
                'student_answer' => 'Internet adalah jaringan global untuk bertukar informasi.',
                'reference_answer' => 'Internet adalah jaringan komputer global yang saling terhubung.',
                'subject' => 'technology',
                'question_type' => 'definition',
                'evaluation_strategy' => 'semantic_similarity',
                'score' => 85,
                'criteria_scores' => [
                    ['name' => 'Definisi', 'score' => 60, 'reason' => 'Konsep utama sudah tepat.'],
                    ['name' => 'Kelengkapan', 'score' => 25, 'reason' => 'Penjelasan cukup lengkap.'],
                ],
                'feedback' => 'Jawaban sudah cukup benar dan terarah.',
                'evaluation_confidence' => 0.9,
            ],
        ],
        'ai_evaluation_result' => [
            'ai_evaluation_status' => 'success',
        ],
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
    ]);

    $result = $service->validate($submission);

    expect($result['submission_id'])->toBe('SUB-POST-EVAL-UNIT-001');
    expect($result['post_evaluation_validation_status'])->toBe('success');
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['validated'])->toBeTrue();
    expect($result['items'][0]['final_score'])->toBe(85);
    expect($result['items'][0]['criteria_validation']['consistent'])->toBeTrue();
    expect($result['items'][0]['requires_manual_review'])->toBeFalse();
    expect($result['warnings'])->toBe([]);
    expect($result['next_stage'])->toBe('result_finalization');
});

test('post evaluation validation marks anomaly as partial and manual review', function () {
    $service = new ValidatePostEvaluationResultService();

    $submission = new Submission([
        'submission_id' => 'SUB-POST-EVAL-UNIT-ANOMALY',
        'ai_evaluation_items' => [
            [
                'question_number' => 1,
                'question' => 'Jelaskan AI',
                'student_answer' => 'AI adalah teknologi.',
                'reference_answer' => 'AI adalah simulasi kecerdasan manusia pada mesin.',
                'subject' => 'technology',
                'question_type' => 'explanation',
                'evaluation_strategy' => 'semantic_similarity',
                'score' => -10,
                'criteria_scores' => [
                    ['name' => 'Konsep', 'score' => 40, 'reason' => 'Cukup.'],
                    ['name' => 'Penjelasan', 'score' => 35, 'reason' => 'Kurang lengkap.'],
                ],
                'feedback' => 'Jawaban sangat baik',
                'evaluation_confidence' => 0.7,
            ],
        ],
        'ai_evaluation_result' => [
            'ai_evaluation_status' => 'success',
        ],
        'rubric_preparation_items' => [
            [
                'constraints' => [
                    'score_range' => [0, 100],
                ],
                'selected_rubric' => [
                    'criteria' => [
                        ['name' => 'Konsep', 'weight' => 60],
                        ['name' => 'Penjelasan', 'weight' => 40],
                    ],
                ],
            ],
        ],
    ]);

    $result = $service->validate($submission);

    expect($result['post_evaluation_validation_status'])->toBe('partial');
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['validation_status'])->toBe('partial');
    expect($result['items'][0]['requires_manual_review'])->toBeTrue();
    expect($result['items'][0]['warnings'])->toContain('score_out_of_range');
    expect($result['items'][0]['warnings'])->toContain('semantic_feedback_mismatch');
});
