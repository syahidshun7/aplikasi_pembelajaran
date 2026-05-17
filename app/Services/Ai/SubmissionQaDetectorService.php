<?php

namespace App\Services\Ai;

class SubmissionQaDetectorService
{
    /**
     * @return array{question_total:int, answered_total:int, unanswered_total:int, answer_completion_rate:float}
     */
    public function detect(string $text): array
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return [
                'question_total' => 0,
                'answered_total' => 0,
                'unanswered_total' => 0,
                'answer_completion_rate' => 0,
            ];
        }

        $lines = preg_split('/\n+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $questionBlocks = [];
        $current = null;

        foreach ($lines as $rawLine) {
            $line = $this->normalizeLine((string) $rawLine);
            if ($line === '') {
                continue;
            }

            $questionMatch = $this->extractQuestion($line);
            if ($questionMatch !== null) {
                if ($current !== null) {
                    $questionBlocks[] = $current;
                }

                $current = [
                    'question' => $questionMatch['question'],
                    'answers' => [],
                ];

                if ($questionMatch['inline_answer'] !== '') {
                    $current['answers'][] = $questionMatch['inline_answer'];
                }

                continue;
            }

            if ($current === null) {
                continue;
            }

            $answer = $this->extractAnswer($line);
            if ($answer !== null) {
                if ($answer !== '') {
                    $current['answers'][] = $answer;
                }
                continue;
            }

            if (empty($current['answers']) && ! $this->looksLikeSectionTitle($line)) {
                $current['answers'][] = $line;
            }
        }

        if ($current !== null) {
            $questionBlocks[] = $current;
        }

        $questionTotal = count($questionBlocks);
        $answeredTotal = collect($questionBlocks)
            ->filter(fn ($block) => $this->hasSubstantiveAnswer((array) ($block['answers'] ?? [])))
            ->count();

        return [
            'question_total' => $questionTotal,
            'answered_total' => $answeredTotal,
            'unanswered_total' => max(0, $questionTotal - $answeredTotal),
            'answer_completion_rate' => $questionTotal > 0
                ? round(($answeredTotal / $questionTotal) * 100, 2)
                : 0,
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\[(TEXT_SUBMISSION|PDF_EXTRACTED_TEXT|FILE_TEXT_EXTRACT|NO_READABLE_ARTIFACT_FOUND)\]/iu', '', $text) ?? $text;
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{question:string, inline_answer:string}|null
     */
    private function extractQuestion(string $line): ?array
    {
        $candidate = '';

        if (preg_match('/^(?:soal|pertanyaan|question)\s*(?:ke\s*)?\d{0,3}\s*[:\-\.)]\s*(.+)$/iu', $line, $matches)) {
            $candidate = trim((string) ($matches[1] ?? ''));
        } elseif (preg_match('/^q\s*\d{0,3}\s*[:\-\.)]\s*(.+)$/iu', $line, $matches)) {
            $candidate = trim((string) ($matches[1] ?? ''));
        } elseif (preg_match('/^\d{1,3}\s*[\)\.\-:]\s*(.+)$/u', $line, $matches)) {
            $candidate = trim((string) ($matches[1] ?? ''));
            if (! $this->looksLikeQuestion($candidate)) {
                return null;
            }
        } elseif (preg_match('/^(?:-|\*)\s*(.+)$/u', $line, $matches)) {
            $candidate = trim((string) ($matches[1] ?? ''));
            if (! $this->looksLikeQuestion($candidate)) {
                return null;
            }
        } else {
            return null;
        }

        if ($candidate === '') {
            return null;
        }

        $inlineAnswer = '';
        if (preg_match('/^(.*?)\s+(?:jawaban|answer|a)\s*[:\-]\s*(.+)$/iu', $candidate, $inlineMatch)) {
            $candidate = trim((string) ($inlineMatch[1] ?? ''));
            $inlineAnswer = trim((string) ($inlineMatch[2] ?? ''));
        }

        if (! $this->looksLikeQuestion($candidate)) {
            return null;
        }

        return [
            'question' => $candidate,
            'inline_answer' => $inlineAnswer,
        ];
    }

    private function extractAnswer(string $line): ?string
    {
        if (preg_match('/^(?:jawaban|answer|a)\s*(?:ke\s*\d{0,3})?\s*[:\-\.)]\s*(.*)$/iu', $line, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    private function looksLikeQuestion(string $value): bool
    {
        $value = $this->normalizeLine($value);
        if ($value === '' || mb_strlen($value) < 6) {
            return false;
        }

        if (str_contains($value, '?')) {
            return true;
        }

        return preg_match('/^(apa|bagaimana|mengapa|kenapa|jelaskan|sebutkan|uraikan|buatkan|implementasikan|what|how|why|explain|describe)\b/iu', $value) === 1;
    }

    /**
     * @param  array<int, string>  $answers
     */
    private function hasSubstantiveAnswer(array $answers): bool
    {
        foreach ($answers as $answer) {
            $answer = $this->normalizeLine((string) $answer);
            if ($answer === '') {
                continue;
            }

            if (preg_match('/^(n\/?a|tidak\s+ada|belum\s+diisi|kosong|-)$/iu', $answer) === 1) {
                continue;
            }

            if (mb_strlen($answer) >= 3) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeSectionTitle(string $value): bool
    {
        $value = $this->normalizeLine($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/^(pendahuluan|latar\s+belakang|kesimpulan|penutup|referensi|daftar\s+pustaka|bab\s+\d+)$/iu', $value) === 1;
    }

    private function normalizeLine(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = preg_replace('/^[\s\*\x{2022}]+/u', '', $value) ?? $value;

        return trim($value);
    }
}
