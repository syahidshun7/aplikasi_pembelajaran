<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class EvaluateSubmissionAnswersService
{
    /**
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,ai_evaluation_status:string,next_stage:string}
     */
    public function evaluate(Submission $submission): array
    {
        $payload = $this->buildPythonPayload($submission);
        $inputPath = $this->writePayloadFile($payload);

        try {
            $output = $this->runPythonEvaluator($inputPath);
            $decoded = json_decode($output, true);

            if (! is_array($decoded)) {
                return $this->failureResult($submission, ['python_ai_evaluator_invalid_json']);
            }

            return $this->normalizeResult($submission, $decoded, $payload['items']);
        } catch (ProcessFailedException|ProcessRuntimeException) {
            return $this->failureResult($submission, ['python_ai_evaluator_process_failed']);
        } catch (Throwable) {
            return $this->failureResult($submission, ['python_ai_evaluator_exception']);
        } finally {
            if (File::exists($inputPath)) {
                File::delete($inputPath);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPythonPayload(Submission $submission): array
    {
        $rubricPreparationResult = is_array($submission->rubric_preparation_result) ? $submission->rubric_preparation_result : [];
        $items = is_array($submission->rubric_preparation_items) ? $submission->rubric_preparation_items : [];

        if ($items === [] && is_array($rubricPreparationResult['items'] ?? null)) {
            $items = $rubricPreparationResult['items'];
        }

        $totalItems = count($items);
        $answeredItems = collect($items)->filter(fn ($item) => trim((string) ($item['student_answer'] ?? '')) !== '' && ! in_array(strtolower(trim((string) ($item['student_answer'] ?? ''))), ['-', '...', 'n/a', 'na'], true))->count();

        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => $items,
            'max_items' => max(1, (int) config('services.ai.ai_evaluation.max_items', 500)),
            'submission_context' => [
                'total_items' => $totalItems,
                'answered_items' => $answeredItems,
                'language' => (string) ($submission->cleaning_language ?: (is_array($submission->cleaning_result) ? ($submission->cleaning_result['language'] ?? 'unknown') : 'unknown')),
                'document_pattern' => (string) (is_array($submission->structure_result) ? ($submission->structure_result['document_pattern'] ?? 'mixed') : 'mixed'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayloadFile(array $payload): string
    {
        $directory = storage_path('app/tmp/submission-ai-evaluation');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.(string) Str::uuid().'.json';
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function runPythonEvaluator(string $inputPath): string
    {
        $pythonBinary = (string) config('services.ai.ai_evaluation.python_binary', 'python');
        $scriptPath = base_path((string) config('services.ai.ai_evaluation.script_path', 'scripts/submission_ai_evaluator.py'));
        $timeout = max(10, (int) config('services.ai.ai_evaluation.timeout_seconds', 30));

        $process = new Process([$pythonBinary, $scriptPath, '--input', $inputPath]);
        $process->setTimeout($timeout);
        $process->setEnv([
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8' => '1',
            'GEMINI_API_KEY' => (string) config('services.ai.gemini.api_key', ''),
            'GEMINI_BASE_URL' => (string) config('services.ai.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/openai'),
            'GEMINI_MODEL' => (string) config('services.ai.gemini.model', 'gemini-2.0-flash-lite'),
        ]);
        $process->mustRun();

        return trim($process->getOutput());
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<int, array<string, mixed>>  $sourceItems
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,ai_evaluation_status:string,next_stage:string}
     */
    private function normalizeResult(Submission $submission, array $decoded, array $sourceItems): array
    {
        $items = $this->normalizeItems($decoded['items'] ?? [], $sourceItems);
        $status = (string) ($decoded['ai_evaluation_status'] ?? 'failed');

        if (! in_array($status, ['success', 'partial', 'failed'], true)) {
            $status = 'failed';
        }
        if ($items === []) {
            $status = 'failed';
        }

        return [
            'submission_id' => (string) (($decoded['submission_id'] ?? null) ?: $submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => $items,
            'warnings' => $this->normalizeStringList($decoded['warnings'] ?? []),
            'ai_evaluation_status' => $status,
            'next_stage' => 'evaluation_quality_review',
        ];
    }

    /**
     * @param  mixed  $items
     * @param  array<int, array<string, mixed>>  $sourceItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(mixed $items, array $sourceItems): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->map(function (array $item, int $index) use ($sourceItems): array {
                $source = is_array($sourceItems[$index] ?? null) ? $sourceItems[$index] : [];

                $score = max(0, min(100, (int) ($item['score'] ?? 0)));
                $criteriaScores = collect(is_array($item['criteria_scores'] ?? null) ? $item['criteria_scores'] : [])
                    ->filter(fn ($row) => is_array($row))
                    ->map(function (array $row): array {
                        return [
                            'name' => trim((string) ($row['name'] ?? '')),
                            'score' => max(0, min(100, (int) ($row['score'] ?? 0))),
                            'reason' => trim((string) ($row['reason'] ?? '')),
                        ];
                    })
                    ->filter(fn (array $row) => $row['name'] !== '')
                    ->values()
                    ->all();

                $strengths = $this->normalizeStringList($item['strengths'] ?? []);
                $weaknesses = $this->normalizeStringList($item['weaknesses'] ?? []);
                $feedback = trim((string) ($item['feedback'] ?? ''));
                if ($feedback === '') {
                    $feedback = 'Jawaban belum dapat dievaluasi.';
                }

                $confidence = round(max(0, min(1, (float) ($item['evaluation_confidence'] ?? 0))), 2);

                $questionNumber = $source['question_number'] ?? null;
                $questionNumber = is_numeric($questionNumber) ? (int) $questionNumber : null;

                return [
                    'question_number' => $questionNumber,
                    'question' => trim((string) ($source['question'] ?? '')),
                    'student_answer' => trim((string) ($source['student_answer'] ?? '')),
                    'reference_answer' => ($source['reference_answer'] ?? null) !== null
                        ? trim((string) ($source['reference_answer'] ?? ''))
                        : null,
                    'subject' => strtolower(trim((string) ($source['subject'] ?? 'other'))),
                    'question_type' => strtolower(trim((string) ($source['question_type'] ?? 'essay'))),
                    'evaluation_strategy' => strtolower(trim((string) ($source['evaluation_strategy'] ?? 'semantic_similarity'))),
                    'score' => $score,
                    'criteria_scores' => $criteriaScores,
                    'strengths' => $strengths,
                    'weaknesses' => $weaknesses,
                    'feedback' => $feedback,
                    'evaluation_confidence' => $confidence,
                ];
            })
            ->all();
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,ai_evaluation_status:string,next_stage:string}
     */
    private function failureResult(Submission $submission, array $warnings): array
    {
        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => [],
            'warnings' => $warnings,
            'ai_evaluation_status' => 'failed',
            'next_stage' => 'evaluation_quality_review',
        ];
    }
}
