<?php

namespace App\Services\Ai;

use Throwable;

class SubmissionQaAiDetectorService
{
    public function __construct(
        private readonly AiProviderGateway $providerGateway,
        private readonly AiResponseJsonParser $jsonParser,
    ) {
    }

    /**
     * @param  array{question_total:int,answered_total:int,unanswered_total:int,answer_completion_rate:float}  $baseline
     * @return array<string, mixed>|null
     */
    public function detect(string $artifactText, array $baseline): ?array
    {
        if (! (bool) config('services.ai.qa_detector.use_ai', true)) {
            return null;
        }

        $normalized = $this->normalizeText($artifactText);
        if ($normalized === '' || ! $this->containsQaHints($normalized)) {
            return null;
        }

        $maxChars = max(1500, (int) config('services.ai.qa_detector.max_chars', 12000));
        if (mb_strlen($normalized) > $maxChars) {
            $normalized = mb_substr($normalized, 0, $maxChars);
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Kamu analyzer hitung soal/jawaban pada laporan submission. Balas HANYA JSON valid tanpa markdown.',
            ],
            [
                'role' => 'user',
                'content' => implode("\n", [
                    'Tugas: hitung jumlah soal dan jumlah soal yang sudah dijawab dari teks laporan berikut.',
                    'Aturan:',
                    '- Pertanyaan bisa berlabel: Soal, Pertanyaan, Question, Q, nomor (1., 2), dst.',
                    '- Jawaban dianggap terisi jika ada isi bermakna (bukan kosong, -, N/A, belum diisi).',
                    '- Jika ragu, pilih hitungan konservatif dan jelaskan di notes.',
                    'Output schema JSON wajib tepat:',
                    '{"question_total":0,"answered_total":0,"notes":"string","confidence":0}',
                    'Baseline parser lokal (untuk referensi, boleh dikoreksi): '.json_encode($baseline, JSON_UNESCAPED_UNICODE),
                    'Teks laporan:',
                    $normalized,
                ]),
            ],
        ];

        try {
            $providerResult = $this->providerGateway->chat($messages);
            $decoded = $this->jsonParser->decode((string) ($providerResult['content'] ?? ''));
            if (! is_array($decoded)) {
                return null;
            }

            $questionTotal = max(0, (int) ($decoded['question_total'] ?? 0));
            $answeredTotal = max(0, (int) ($decoded['answered_total'] ?? 0));
            $answeredTotal = min($answeredTotal, $questionTotal);

            if ($questionTotal <= 0) {
                return null;
            }

            $completion = $questionTotal > 0
                ? round(($answeredTotal / $questionTotal) * 100, 2)
                : 0;

            return [
                'question_total' => $questionTotal,
                'answered_total' => $answeredTotal,
                'unanswered_total' => max(0, $questionTotal - $answeredTotal),
                'answer_completion_rate' => $completion,
                'notes' => trim((string) ($decoded['notes'] ?? '')),
                'confidence' => max(0, min(100, (int) ($decoded['confidence'] ?? 0))),
                'provider_used' => (string) ($providerResult['provider_used'] ?? ''),
                'is_fallback' => (bool) ($providerResult['is_fallback'] ?? false),
                'latency_ms' => (int) ($providerResult['latency_ms'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\[(TEXT_SUBMISSION|PDF_EXTRACTED_TEXT|FILE_TEXT_EXTRACT|NO_READABLE_ARTIFACT_FOUND)\]/iu', '', $text) ?? $text;
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function containsQaHints(string $text): bool
    {
        return preg_match('/\b(soal|pertanyaan|question|jawaban|answer)\b|\bq\s*\d{1,3}\b|(^|\n)\s*\d{1,3}\s*[\)\.:-]/iu', $text) === 1;
    }
}

