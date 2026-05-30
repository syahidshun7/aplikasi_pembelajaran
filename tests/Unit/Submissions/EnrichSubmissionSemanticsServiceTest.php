<?php

use App\Models\Submission;
use App\Services\Submissions\EnrichSubmissionSemanticsService;

uses(Tests\TestCase::class);

test('semantic enrichment classifies structured items without grading answers', function () {
    $service = new EnrichSubmissionSemanticsService();

    $submission = new Submission([
        'submission_id' => 'SUB-SEM-UNIT-001',
        'structured_items' => [
            [
                'question_number' => 1,
                'question' => 'Apa itu internet?',
                'answer' => 'Internet adalah jaringan global.',
                'answer_status' => 'filled',
                'is_empty' => false,
            ],
            [
                'question_number' => 2,
                'question' => 'Hitung 10 + 5',
                'answer' => '15',
                'answer_status' => 'filled',
                'is_empty' => false,
            ],
        ],
        'structure_result' => [
            'structure_detection_status' => 'success',
            'warnings' => [],
        ],
    ]);

    $result = $service->enrich($submission);

    expect($result['submission_id'])->toBe('SUB-SEM-UNIT-001');
    expect($result['semantic_enrichment_status'])->toBe('success');
    expect($result['items'])->toHaveCount(2);

    expect($result['items'][0]['language'])->toBe('id');
    expect($result['items'][0]['subject'])->toBe('technology');
    expect($result['items'][0]['question_type'])->toBe('definition');
    expect($result['items'][0]['difficulty'])->toBe('low');
    expect($result['items'][0]['evaluation_strategy'])->toBe('semantic_similarity');
    expect($result['items'][0]['expected_concepts'])->not->toBeEmpty();
    expect($result['items'][0]['semantic_tags'])->toContain('technology');

    expect($result['items'][1]['question_type'])->toBe('calculation');
    expect($result['items'][1]['evaluation_strategy'])->toBe('rule_based_evaluation');
    expect($result['items'][1]['difficulty'])->toBe('low');

    $conceptWeightTotal = array_reduce(
        $result['items'][1]['expected_concepts'],
        fn (float $sum, array $row): float => $sum + (float) ($row['weight'] ?? 0),
        0.0,
    );
    expect(round($conceptWeightTotal, 3))->toBe(1.0);

    expect($result['items'][0]['confidence'])->toBeGreaterThan(0);
    expect($result['items'][0]['confidence'])->toBeLessThanOrEqual(1);
    expect($result['next_stage'])->toBe('rubric_preparation');
});

test('semantic enrichment marks empty answers as low confidence metadata', function () {
    $service = new EnrichSubmissionSemanticsService();

    $submission = new Submission([
        'submission_id' => 'SUB-SEM-EMPTY',
        'structured_items' => [
            [
                'question_number' => 1,
                'question' => 'Jelaskan AI dalam pendidikan',
                'answer' => '-',
                'answer_status' => 'empty',
                'is_empty' => true,
            ],
        ],
        'structure_result' => [
            'structure_detection_status' => 'success',
            'warnings' => [],
        ],
    ]);

    $result = $service->enrich($submission);

    expect($result['semantic_enrichment_status'])->toBe('partial');
    expect($result['items'][0]['answer_length'])->toBe('empty');
    expect($result['items'][0]['answer_quality'])->toBe('low_confidence');
    expect($result['items'][0]['question_type'])->toBe('explanation');
    expect($result['warnings'])->toContain('empty_answer_detected');
});
