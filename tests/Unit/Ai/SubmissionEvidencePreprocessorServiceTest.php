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

test('submission evidence preprocessor keeps per-question snippets from task-bank answers even when artifact text is minimal', function () {
    $service = new SubmissionEvidencePreprocessorService();

    $result = $service->preprocess(
        normalizedText: "[TEXT_SUBMISSION]\n[TASK_BANK_SUBMISSION]",
        rubricContext: null,
        taskBankContext: [
            'questions' => [
                [
                    'uuid' => 'q-essay-1',
                    'question_type' => 'essay',
                    'question_text' => 'Jelaskan fungsi migration di Laravel',
                    'user_answer' => 'Migration dipakai untuk versioning struktur database agar sinkron antar environment.',
                ],
            ],
        ],
        artifactWarnings: [],
        sourceFlags: ['text'],
    );

    $evidence = $result['task_bank_evidence'][0] ?? [];

    expect($result['task_bank_evidence'])->not->toBeEmpty();
    expect((bool) ($evidence['has_user_answer'] ?? false))->toBeTrue();
    expect((array) ($evidence['snippets'] ?? []))->not->toBeEmpty();
    expect((string) (($evidence['snippets'][0] ?? '')))->toContain('Migration dipakai');
    expect((int) ($evidence['match_count'] ?? 0))->toBeGreaterThan(0);
});

test('submission evidence preprocessor prefers strict snippet sections by question uuid from task-bank answer narrative', function () {
    $service = new SubmissionEvidencePreprocessorService();

    $normalizedText = implode("\n", [
        '[TEXT_SUBMISSION]',
        '[TASK_BANK_SUBMISSION]',
        '',
        '[TASK_BANK_ANSWERS]',
        'Q1 | uuid=q-uuid-1 | type=essay | weight=1',
        'QUESTION: Jelaskan fungsi migration.',
        'ANSWER: Migration dipakai untuk versioning schema database.',
        '',
        'Q2 | uuid=q-uuid-2 | type=essay | weight=1',
        'QUESTION: Jelaskan fungsi middleware.',
        'ANSWER: Middleware menyaring request sebelum controller.',
    ]);

    $result = $service->preprocess(
        normalizedText: $normalizedText,
        rubricContext: null,
        taskBankContext: [
            'questions' => [
                [
                    'uuid' => 'q-uuid-1',
                    'question_type' => 'essay',
                    'question_text' => 'Jelaskan fungsi migration.',
                    'user_answer' => 'Migration dipakai untuk versioning schema database.',
                ],
                [
                    'uuid' => 'q-uuid-2',
                    'question_type' => 'essay',
                    'question_text' => 'Jelaskan fungsi middleware.',
                    'user_answer' => 'Middleware menyaring request sebelum controller.',
                ],
            ],
        ],
        artifactWarnings: [],
        sourceFlags: ['text'],
    );

    $evidenceByUuid = collect($result['task_bank_evidence'] ?? [])->keyBy('question_uuid');
    $q1 = $evidenceByUuid->get('q-uuid-1');
    $q2 = $evidenceByUuid->get('q-uuid-2');

    expect($q1)->not->toBeNull();
    expect($q2)->not->toBeNull();
    expect((string) (($q1['snippets'][0] ?? '')))->toContain('QUESTION: Jelaskan fungsi migration.');
    expect((string) (($q1['snippets'][0] ?? '')))->not->toContain('middleware');
    expect((string) (($q2['snippets'][0] ?? '')))->toContain('QUESTION: Jelaskan fungsi middleware.');
    expect((string) (($q2['snippets'][0] ?? '')))->not->toContain('migration');
});

test('submission evidence preprocessor computes stronger overall confidence when rubric is absent but task-bank evidence is strong', function () {
    $service = new SubmissionEvidencePreprocessorService();

    $normalizedText = implode("\n", [
        '[TASK_BANK_ANSWERS]',
        'Q1 | uuid=q-only-1 | type=essay | weight=1',
        'QUESTION: Jelaskan fungsi transaction database.',
        'ANSWER: Transaction menjaga konsistensi data saat beberapa operasi harus sukses bersama.',
    ]);

    $result = $service->preprocess(
        normalizedText: $normalizedText,
        rubricContext: null,
        taskBankContext: [
            'questions' => [
                [
                    'uuid' => 'q-only-1',
                    'question_type' => 'essay',
                    'question_text' => 'Jelaskan fungsi transaction database.',
                    'user_answer' => 'Transaction menjaga konsistensi data.',
                ],
            ],
        ],
        artifactWarnings: [],
        sourceFlags: ['text'],
    );

    expect((int) data_get($result, 'confidence.task_bank', 0))->toBeGreaterThan(70);
    expect((int) data_get($result, 'confidence.rubric', 0))->toBe(0);
    expect((int) data_get($result, 'confidence.overall', 0))->toBeGreaterThanOrEqual(70);
});
