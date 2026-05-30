<?php

use App\Models\Submission;
use App\Services\Submissions\CleanSubmissionTextService;

uses(Tests\TestCase::class);

test('clean submission text normalizes extracted text without changing meaning', function () {
    $service = new CleanSubmissionTextService();

    $submission = new Submission([
        'submission_id' => 'SUB-CLEAN-001',
        'extracted_text' => "Page 1 of 2\n1. Apa\titu\rlnternet?\nlnternet adalah\njaringan komputer\nglobal.\npe mbelajaran digital",
        'extraction_result' => [
            'extraction_status' => 'success',
            'warnings' => [],
            'ocr_confidence' => null,
        ],
    ]);

    $result = $service->clean($submission);

    expect($result['submission_id'])->toBe('SUB-CLEAN-001');
    expect($result['cleaning_status'])->toBe('success');
    expect($result['clean_text'])->toContain('1. Apa itu Internet?');
    expect($result['clean_text'])->toContain('Internet adalah jaringan komputer global.');
    expect($result['clean_text'])->toContain('pembelajaran digital');
    expect($result['language'])->toBe('id');
    expect($result['changes_summary']['noise_removed'])->toBeGreaterThan(0);
    expect($result['changes_summary']['ocr_corrections'])->toBeGreaterThanOrEqual(2);
    expect($result['changes_summary']['line_break_fixed'])->toBeGreaterThan(0);
    expect($result['changes_summary']['garbage_removed'])->toBe(1);
    expect($result['next_stage'])->toBe('structure_detection');
});

test('clean submission text returns failed json when raw text is empty', function () {
    $service = new CleanSubmissionTextService();

    $submission = new Submission([
        'submission_id' => 'SUB-CLEAN-EMPTY',
        'extracted_text' => '',
        'extraction_result' => [
            'extraction_status' => 'failed',
            'warnings' => ['no_readable_text'],
        ],
    ]);

    $result = $service->clean($submission);

    expect($result['cleaning_status'])->toBe('failed');
    expect($result['clean_text'])->toBe('');
    expect($result['warnings'])->toContain('empty_clean_text');
    expect($result['next_stage'])->toBe('structure_detection');
});
