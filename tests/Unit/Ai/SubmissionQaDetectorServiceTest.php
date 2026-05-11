<?php

use App\Services\Ai\SubmissionQaDetectorService;

test('submission qa detector infers question and answered totals from report-like text', function () {
    $service = new SubmissionQaDetectorService();

    $text = implode("\n", [
        '[PDF_EXTRACTED_TEXT]',
        'Soal 1: Apa kepanjangan dari HTTP?',
        'Jawaban: Hypertext Transfer Protocol.',
        '2. Jelaskan fungsi status code 404?',
        '3) Mengapa validasi input diperlukan?',
        'A: Untuk mencegah data tidak valid masuk ke sistem.',
    ]);

    $result = $service->detect($text);

    expect($result['question_total'])->toBe(3);
    expect($result['answered_total'])->toBe(2);
    expect($result['unanswered_total'])->toBe(1);
    expect((float) $result['answer_completion_rate'])->toBe(66.67);
});

test('submission qa detector returns zero when no question pattern found', function () {
    $service = new SubmissionQaDetectorService();

    $result = $service->detect('Ini adalah ringkasan proyek tanpa format tanya jawab.');

    expect($result['question_total'])->toBe(0);
    expect($result['answered_total'])->toBe(0);
    expect($result['unanswered_total'])->toBe(0);
    expect((float) $result['answer_completion_rate'])->toBe(0.0);
});

