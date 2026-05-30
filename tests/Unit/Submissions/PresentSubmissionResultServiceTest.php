<?php

use App\Models\Submission;
use App\Services\Submissions\PresentSubmissionResultService;

uses(Tests\TestCase::class);

test('result presentation builds mentor friendly output from validated evaluation data', function () {
    $service = new PresentSubmissionResultService();

    $submission = new Submission([
        'submission_id' => 'SUB-PRES-UNIT-001',
        'post_evaluation_validation_items' => [
            [
                'question_number' => 1,
                'question' => 'Apa itu internet?',
                'student_answer' => 'Internet adalah jaringan global untuk bertukar informasi.',
                'reference_answer' => 'Internet adalah jaringan komputer global yang saling terhubung.',
                'subject' => 'technology',
                'question_type' => 'definition',
                'score' => 85,
                'final_score' => 85,
                'normalized_score' => 85,
                'confidence' => 0.91,
                'requires_manual_review' => false,
                'retry_count' => 0,
                'final_feedback' => 'Jawaban sudah benar namun masih kurang detail tambahan.',
            ],
        ],
        'post_evaluation_validation_result' => [
            'post_evaluation_validation_status' => 'success',
            'warnings' => [],
        ],
        'ai_evaluation_items' => [
            [
                'criteria_scores' => [
                    ['name' => 'Definisi', 'score' => 60, 'reason' => 'Konsep utama sudah benar.'],
                    ['name' => 'Kelengkapan', 'score' => 25, 'reason' => 'Masih perlu detail tambahan.'],
                ],
                'strengths' => ['Konsep utama sudah sesuai'],
                'weaknesses' => ['Tambahkan contoh agar lebih lengkap'],
                'feedback' => 'Jawaban sudah baik dan sesuai konteks pertanyaan.',
                'evaluation_confidence' => 0.91,
            ],
        ],
        'semantic_items' => [
            [
                'complexity' => 'low',
                'tags' => ['technology', 'internet', 'definition'],
            ],
        ],
    ]);

    $result = $service->present($submission);

    expect($result['submission_id'])->toBe('SUB-PRES-UNIT-001');
    expect($result['result_presentation_status'])->toBe('success');
    expect($result['next_stage'])->toBe('mentor_verdict');
    expect($result['items'])->toHaveCount(1);

    expect($result['items'][0]['presentation_status'])->toBe('success');
    expect($result['items'][0]['submission_status'])->toBe('evaluated');
    expect($result['items'][0]['mentor_view']['final_score'])->toBe(85);
    expect($result['items'][0]['mentor_view']['score_label'])->toBe('Excellent');
    expect($result['items'][0]['confidence_display']['value'])->toBe(0.91);
    expect($result['items'][0]['confidence_display']['level'])->toBe('high');
    expect($result['items'][0]['confidence_display']['requires_manual_review'])->toBeFalse();
    expect($result['warnings'])->toBe([]);
});

test('result presentation marks manual review items as partial output', function () {
    $service = new PresentSubmissionResultService();

    $submission = new Submission([
        'submission_id' => 'SUB-PRES-UNIT-LOWCONF',
        'post_evaluation_validation_items' => [
            [
                'question_number' => 1,
                'question' => 'Jelaskan AI',
                'student_answer' => 'AI adalah teknologi.',
                'reference_answer' => 'AI adalah simulasi kecerdasan manusia pada mesin.',
                'subject' => 'technology',
                'question_type' => 'explanation',
                'score' => 62,
                'final_score' => 62,
                'normalized_score' => 62,
                'confidence' => 0.45,
                'requires_manual_review' => true,
                'retry_count' => 1,
                'final_feedback' => 'Jawaban cukup relevan namun masih sangat singkat.',
            ],
        ],
        'post_evaluation_validation_result' => [
            'post_evaluation_validation_status' => 'partial',
            'warnings' => ['semantic_feedback_mismatch'],
        ],
        'ai_evaluation_items' => [
            [
                'criteria_scores' => [
                    ['name' => 'Konsep', 'score' => 40, 'reason' => 'Konsep dasar terlihat.'],
                    ['name' => 'Penjelasan', 'score' => 22, 'reason' => 'Perlu detail lebih lanjut.'],
                ],
                'feedback' => 'Jawaban masih perlu pendalaman.',
                'evaluation_confidence' => 0.45,
            ],
        ],
        'semantic_items' => [
            [
                'complexity' => 'medium',
                'tags' => ['technology', 'ai'],
            ],
        ],
    ]);

    $result = $service->present($submission);

    expect($result['result_presentation_status'])->toBe('partial');
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['presentation_status'])->toBe('partial');
    expect($result['items'][0]['confidence_display']['level'])->toBe('low');
    expect($result['items'][0]['confidence_display']['requires_manual_review'])->toBeTrue();
    expect($result['items'][0]['warnings'])->toContain('manual_review_required');
    expect($result['warnings'])->toContain('manual_review_required');
});
