<?php

namespace App\Services\Ai;

class SubmissionReportQaDetectorService
{
    /**
     * @return array<int, array<string, string>>
     */
    public function extract(string $text, int $maxPairs = 40): array
    {
        $normalizedText = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($normalizedText === '' || $normalizedText === '[NO_READABLE_ARTIFACT_FOUND]') {
            return [];
        }

        $lines = collect(explode("\n", $normalizedText))
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();

        $pairs = [];
        $currentQuestion = '';
        $currentAnswer = '';
        $collecting = 'none';

        $flushCurrent = function () use (&$pairs, &$currentQuestion, &$currentAnswer, &$collecting, $maxPairs): void {
            if (count($pairs) >= $maxPairs) {
                return;
            }

            $question = $this->clip($currentQuestion, 700);
            $answer = $this->clip($currentAnswer, 900);

            if ($question !== '') {
                $pairs[] = [
                    'question_text' => $question,
                    'user_answer' => $answer,
                ];
            }

            $currentQuestion = '';
            $currentAnswer = '';
            $collecting = 'none';
        };

        foreach ($lines as $line) {
            if ($this->isQuestionLine($line)) {
                $flushCurrent();
                [$question, $inlineAnswer] = $this->extractQuestionAndInlineAnswer($line);
                $currentQuestion = $question;
                $currentAnswer = $inlineAnswer;
                $collecting = $inlineAnswer !== '' ? 'answer' : 'question';
                continue;
            }

            if ($this->isAnswerLine($line)) {
                $answerText = $this->extractAnswerText($line);
                if ($currentQuestion === '') {
                    continue;
                }

                $currentAnswer = trim($currentAnswer.' '.$answerText);
                $collecting = 'answer';
                continue;
            }

            if ($currentQuestion === '') {
                continue;
            }

            if ($collecting === 'question') {
                if (mb_strlen($currentQuestion) <= 680) {
                    $currentQuestion = trim($currentQuestion.' '.$line);
                }
                continue;
            }

            if ($collecting === 'answer') {
                if (mb_strlen($currentAnswer) <= 880) {
                    $currentAnswer = trim($currentAnswer.' '.$line);
                }
            }
        }

        $flushCurrent();

        return collect($pairs)
            ->filter(fn ($pair) => trim((string) ($pair['question_text'] ?? '')) !== '')
            ->take($maxPairs)
            ->values()
            ->all();
    }

    private function isQuestionLine(string $line): bool
    {
        if (preg_match('/^\s*(?:soal|pertanyaan|question|q)\s*[0-9ivxlcdm]*\s*[\)\].:\-]?\s+.+$/iu', $line) === 1) {
            return true;
        }

        if (preg_match('/^\s*\d{1,3}[\).:\-]\s+.+$/u', $line) === 1) {
            return str_contains($line, '?')
                || preg_match('/\b(?:apa|jelaskan|sebutkan|mengapa|bagaimana|what|why|how)\b/iu', $line) === 1;
        }

        return false;
    }

    private function isAnswerLine(string $line): bool
    {
        return preg_match('/^\s*(?:jawab(?:an)?|answer|a)\s*[0-9ivxlcdm]*\s*[\)\].:\-]?\s+.+$/iu', $line) === 1;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function extractQuestionAndInlineAnswer(string $line): array
    {
        $line = trim($line);

        $question = preg_replace('/^\s*(?:soal|pertanyaan|question|q)\s*[0-9ivxlcdm]*\s*[\)\].:\-]?\s*/iu', '', $line) ?? $line;
        $question = preg_replace('/^\s*\d{1,3}[\).:\-]\s*/u', '', $question) ?? $question;
        $question = trim($question);

        $inlineAnswer = '';
        if (preg_match('/^(.*?)\s+(?:jawab(?:an)?|answer|a)\s*[\)\].:\-]\s*(.+)$/iu', $question, $matches) === 1) {
            $question = trim((string) ($matches[1] ?? ''));
            $inlineAnswer = trim((string) ($matches[2] ?? ''));
        }

        return [$this->clip($question, 700), $this->clip($inlineAnswer, 900)];
    }

    private function extractAnswerText(string $line): string
    {
        $answer = preg_replace('/^\s*(?:jawab(?:an)?|answer|a)\s*[0-9ivxlcdm]*\s*[\)\].:\-]?\s*/iu', '', $line) ?? $line;

        return $this->clip(trim($answer), 900);
    }

    private function clip(string $value, int $maxChars): string
    {
        $trimmed = trim($value);
        if (mb_strlen($trimmed) <= $maxChars) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, $maxChars);
    }
}

