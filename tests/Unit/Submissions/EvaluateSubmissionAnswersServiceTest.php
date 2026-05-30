<?php

use App\Models\Submission;
use App\Services\Submissions\EvaluateSubmissionAnswersService;

uses(Tests\TestCase::class);

test('ai evaluation scores answers using rubric criteria and returns valid json metadata', function () {
    $service = new EvaluateSubmissionAnswersService();

    $submission = new Submission([
        'submission_id' => 'SUB-EVAL-UNIT-001',
        'rubric_preparation_items' => [
            [
                'question_number' => 1,
                'question' => 'Apa itu internet?',
                'student_answer' => 'Internet adalah jaringan global untuk komunikasi data.',
                'reference_answer' => 'Internet adalah jaringan komputer global yang saling terhubung.',
                'subject' => 'technology',
                'question_type' => 'definition',
                'evaluation_strategy' => 'semantic_similarity',
                'selected_rubric' => [
                    'rubric_id' => 'RUB-UNIT-1',
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
                'payload_status' => 'ready',
            ],
        ],
        'rubric_preparation_result' => [
            'rubric_preparation_status' => 'success',
        ],
    ]);

    $result = $service->evaluate($submission);

    expect($result['submission_id'])->toBe('SUB-EVAL-UNIT-001');
    expect($result['ai_evaluation_status'])->toBe('success');
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['question_number'])->toBe(1);
    expect($result['items'][0]['score'])->toBeGreaterThan(0);
    expect($result['items'][0]['score'])->toBeLessThanOrEqual(100);
    expect($result['items'][0]['criteria_scores'])->toHaveCount(2);
    expect(trim((string) $result['items'][0]['feedback']))->not->toBe('');
    expect($result['items'][0]['evaluation_confidence'])->toBeGreaterThan(0);
    expect($result['items'][0]['evaluation_confidence'])->toBeLessThanOrEqual(1);
    expect($result['next_stage'])->toBe('evaluation_quality_review');
});

test('ai evaluation returns zero score and short feedback for empty answers', function () {
    $service = new EvaluateSubmissionAnswersService();

    $submission = new Submission([
        'submission_id' => 'SUB-EVAL-EMPTY',
        'rubric_preparation_items' => [
            [
                'question_number' => 1,
                'question' => 'Jelaskan AI',
                'student_answer' => '-',
                'reference_answer' => 'AI adalah simulasi kecerdasan manusia pada mesin.',
                'subject' => 'technology',
                'question_type' => 'explanation',
                'evaluation_strategy' => 'semantic_similarity',
                'selected_rubric' => [
                    'rubric_id' => 'RUB-UNIT-2',
                    'criteria' => [
                        ['name' => 'Konsep', 'weight' => 60],
                        ['name' => 'Penjelasan', 'weight' => 40],
                    ],
                ],
                'constraints' => [
                    'score_range' => [0, 100],
                    'allowed_feedback_length' => 200,
                    'strict_json_output' => true,
                ],
                'payload_status' => 'ready',
            ],
        ],
        'rubric_preparation_result' => [
            'rubric_preparation_status' => 'success',
        ],
    ]);

    $result = $service->evaluate($submission);

    expect($result['ai_evaluation_status'])->toBe('partial');
    expect($result['items'][0]['score'])->toBe(0);
    expect($result['items'][0]['feedback'])->toBe('Jawaban belum diisi.');
});
