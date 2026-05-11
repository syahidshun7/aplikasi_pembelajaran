<?php

use App\Services\Ai\SubmissionEvidencePreprocessorService;

test('submission evidence preprocessor builds rubric and taskbank evidence with confidence', function () {
    $service = new SubmissionEvidencePreprocessorService();

    $text = 'Struktur dan logika kode dijelaskan dengan contoh route dan controller. '
        .'Status code 404 digunakan saat resource tidak ditemukan. '
        .'Mahasiswa juga menjelaskan validasi input dan flow request.';

    $rubricContext = [
        'criteria' => [
            ['id' => 1, 'name' => 'Struktur dan Logika Kode'],
        ],
        'matrix' => [
            ['criteria_id' => 1, 'description' => 'Penjelasan struktur kode dan logika program.'],
        ],
    ];

    $taskBankContext = [
        'questions' => [
            [
                'uuid' => 'q-1',
                'question_type' => 'multiple_choice',
                'question_text' => 'Kapan status code 404 digunakan?',
                'user_answer' => 'Saat resource tidak ditemukan',
            ],
        ],
    ];

    $result = $service->preprocess(
        normalizedText: $text,
        rubricContext: $rubricContext,
        taskBankContext: $taskBankContext,
        artifactWarnings: [],
        sourceFlags: ['text'],
    );

    expect((float) $result['quality_score'])->toBeGreaterThan(0.2);
    expect((int) ($result['confidence']['overall'] ?? 0))->toBeGreaterThan(0);
    expect($result['rubric_evidence'])->not->toBeEmpty();
    expect($result['task_bank_evidence'])->not->toBeEmpty();
    expect((int) ($result['rubric_evidence'][0]['confidence'] ?? 0))->toBeGreaterThan(0);
    expect((int) ($result['task_bank_evidence'][0]['confidence'] ?? 0))->toBeGreaterThan(0);
});

