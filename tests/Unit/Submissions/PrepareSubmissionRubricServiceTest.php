<?php

use App\Models\Submission;
use App\Services\Submissions\PrepareSubmissionRubricService;

uses(Tests\TestCase::class);

test('rubric preparation builds stable payload metadata without grading answers', function () {
    $service = new PrepareSubmissionRubricService();

    $submission = new Submission([
        'submission_id' => 'SUB-RUB-UNIT-001',
        'semantic_items' => [
            [
                'question_number' => 1,
                'question' => 'Apa itu internet?',
                'answer' => 'Internet adalah jaringan global.',
                'subject' => 'technology',
                'question_type' => 'definition',
                'evaluation_strategy' => 'semantic_similarity',
            ],
        ],
        'semantic_result' => [
            'semantic_enrichment_status' => 'success',
        ],
    ]);

    $result = $service->prepare($submission);

    expect($result['submission_id'])->toBe('SUB-RUB-UNIT-001');
    expect($result['rubric_preparation_status'])->toBe('partial');
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['question_number'])->toBe(1);
    expect($result['items'][0]['evaluation_strategy'])->toBe('semantic_similarity');
    expect($result['items'][0]['selected_rubric']['rubric_id'])->toBeNull();
    expect($result['items'][0]['payload_status'])->toBe('partial');
    expect($result['next_stage'])->toBe('ai_evaluation');
});

test('rubric preparation fails safely when semantic items are missing', function () {
    $service = new PrepareSubmissionRubricService();

    $submission = new Submission([
        'submission_id' => 'SUB-RUB-UNIT-EMPTY',
        'semantic_items' => [],
        'semantic_result' => [
            'semantic_enrichment_status' => 'success',
        ],
    ]);

    $result = $service->prepare($submission);

    expect($result['rubric_preparation_status'])->toBe('failed');
    expect($result['items'])->toBe([]);
    expect($result['warnings'])->toContain('missing_semantic_items');
});
