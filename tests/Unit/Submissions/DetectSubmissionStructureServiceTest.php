<?php

use App\Models\Submission;
use App\Services\Submissions\DetectSubmissionStructureService;

uses(Tests\TestCase::class);

test('detect submission structure segments numbered clean text without grading', function () {
    $service = new DetectSubmissionStructureService();

    $submission = new Submission([
        'submission_id' => 'SUB-STRUCT-UNIT',
        'clean_text' => "Kerjakan semua soal berikut!\nNama: Budi\n1. Apa itu Internet?\nInternet adalah jaringan global.\n\n2. Jelaskan AI\n-",
        'cleaning_result' => [
            'cleaning_status' => 'success',
            'warnings' => [],
        ],
    ]);

    $result = $service->detect($submission);

    expect($result['submission_id'])->toBe('SUB-STRUCT-UNIT');
    expect($result['document_pattern'])->toBe('numbered_list');
    expect($result['structure_detection_status'])->toBe('success');
    expect($result['items'])->toHaveCount(2);
    expect($result['items'][0]['question_number'])->toBe(1);
    expect($result['items'][0]['question'])->toBe('Apa itu Internet?');
    expect($result['items'][0]['answer'])->toBe('Internet adalah jaringan global.');
    expect($result['items'][0]['answer_status'])->toBe('filled');
    expect($result['items'][1]['question_number'])->toBe(2);
    expect($result['items'][1]['answer'])->toBe('-');
    expect($result['items'][1]['answer_status'])->toBe('empty');
    expect($result['items'][1]['is_empty'])->toBeTrue();
    expect($result['instruction_blocks'])->toContain('Kerjakan semua soal berikut!');
    expect($result['instruction_blocks'])->toContain('Nama: Budi');
    expect($result['next_stage'])->toBe('semantic_enrichment');
});

test('detect submission structure treats answer only text as essay block', function () {
    $service = new DetectSubmissionStructureService();

    $submission = new Submission([
        'submission_id' => 'SUB-STRUCT-ESSAY',
        'clean_text' => 'Internet adalah jaringan global yang menghubungkan komputer.',
        'cleaning_result' => [
            'cleaning_status' => 'success',
            'warnings' => [],
        ],
    ]);

    $result = $service->detect($submission);

    expect($result['document_pattern'])->toBe('essay_block');
    expect($result['structure_detection_status'])->toBe('success');
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['question_number'])->toBeNull();
    expect($result['items'][0]['question'])->toBeNull();
    expect($result['items'][0]['answer'])->toBe('Internet adalah jaringan global yang menghubungkan komputer.');
    expect($result['items'][0]['answer_status'])->toBe('filled');
});
