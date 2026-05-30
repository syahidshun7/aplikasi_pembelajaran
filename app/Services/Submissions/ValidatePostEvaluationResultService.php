<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ValidatePostEvaluationResultService
{
    /**
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,post_evaluation_validation_status:string,next_stage:string}
     */
    public function validate(Submission $submission): array
    {
        $payload = $this->buildPythonPayload($submission);
        $inputPath = $this->writePayloadFile($payload);

        try {
            $output = $this->runPythonValidator($inputPath);
            $decoded = json_decode($output, true);

            if (! is_array($decoded)) {
                return $this->failureResult($submission, ['python_post_evaluation_validator_invalid_json']);
            }

            return $this->normalizeResult($submission, $decoded, $payload['source_items']);
        } catch (ProcessFailedException|ProcessRuntimeException) {
            return $this->failureResult($submission, ['python_post_evaluation_validator_process_failed']);
        } catch (Throwable) {
            return $this->failureResult($submission, ['python_post_evaluation_validator_exception']);
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
        $aiEvaluationResult = is_array($submission->ai_evaluation_result) ? $submission->ai_evaluation_result : [];
        $aiItems = is_array($submission->ai_evaluation_items) ? $submission->ai_evaluation_items : [];
        if ($aiItems === [] && is_array($aiEvaluationResult['items'] ?? null)) {
            $aiItems = $aiEvaluationResult['items'];
        }

        $rubricPreparationResult = is_array($submission->rubric_preparation_result) ? $submission->rubric_preparation_result : [];
        $rubricItems = is_array($submission->rubric_preparation_items) ? $submission->rubric_preparation_items : [];
        if ($rubricItems === [] && is_array($rubricPreparationResult['items'] ?? null)) {
            $rubricItems = $rubricPreparationResult['items'];
        }

        $payloadItems = [];

        foreach ($aiItems as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $rubricSource = is_array($rubricItems[$index] ?? null) ? $rubricItems[$index] : [];
            $rubricInfo = is_array($rubricSource['selected_rubric'] ?? null) ? $rubricSource['selected_rubric'] : [];

            $payloadItems[] = [
                'score' => $item['score'] ?? 0,
                'criteria_scores' => is_array($item['criteria_scores'] ?? null) ? $item['criteria_scores'] : [],
                'feedback' => (string) ($item['feedback'] ?? ''),
                'constraints' => is_array($rubricSource['constraints'] ?? null) ? $rubricSource['constraints'] : ['score_range' => [0, 100]],
                'rubric_criteria' => is_array($rubricInfo['criteria'] ?? null) ? $rubricInfo['criteria'] : [],
            ];
        }

        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => $payloadItems,
            'source_items' => $aiItems,
            'max_items' => max(1, (int) config('services.ai.post_evaluation_validation.max_items', 500)),
            'max_retries' => max(0, min(3, (int) config('services.ai.post_evaluation_validation.max_retries', 2))),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayloadFile(array $payload): string
    {
        $directory = storage_path('app/tmp/submission-post-evaluation-validation');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.(string) Str::uuid().'.json';
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function runPythonValidator(string $inputPath): string
    {
        $pythonBinary = (string) config('services.ai.post_evaluation_validation.python_binary', 'python');
        $scriptPath = base_path((string) config('services.ai.post_evaluation_validation.script_path', 'scripts/submission_post_evaluation_validator.py'));
        $timeout = max(10, (int) config('services.ai.post_evaluation_validation.timeout_seconds', 30));

        $process = new Process([$pythonBinary, $scriptPath, '--input', $inputPath]);
        $process->setTimeout($timeout);
        $process->setEnv([
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8' => '1',
        ]);
        $process->mustRun();

        return trim($process->getOutput());
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<int, array<string, mixed>>  $sourceItems
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,post_evaluation_validation_status:string,next_stage:string}
     */
    private function normalizeResult(Submission $submission, array $decoded, array $sourceItems): array
    {
        $items = $this->normalizeItems($decoded['items'] ?? [], $sourceItems);
        $status = (string) ($decoded['post_evaluation_validation_status'] ?? 'failed');

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
            'post_evaluation_validation_status' => $status,
            'next_stage' => 'result_finalization',
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

                $questionNumber = $source['question_number'] ?? null;
                $questionNumber = is_numeric($questionNumber) ? (int) $questionNumber : null;

                $score = max(0, min(100, (int) ($source['score'] ?? 0)));

                $criteriaValidation = is_array($item['criteria_validation'] ?? null) ? $item['criteria_validation'] : [];
                $feedbackValidation = is_array($item['feedback_validation'] ?? null) ? $item['feedback_validation'] : [];

                $validationStatus = strtolower(trim((string) ($item['validation_status'] ?? 'failed')));
                if (! in_array($validationStatus, ['success', 'partial', 'failed'], true)) {
                    $validationStatus = 'failed';
                }

                $feedbackQuality = strtolower(trim((string) ($feedbackValidation['quality'] ?? 'low')));
                if (! in_array($feedbackQuality, ['high', 'normal', 'low'], true)) {
                    $feedbackQuality = 'low';
                }

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
                    'validated' => (bool) ($item['validated'] ?? false),
                    'final_score' => max(0, min(100, (int) ($item['final_score'] ?? 0))),
                    'normalized_score' => max(0, min(100, (int) ($item['normalized_score'] ?? 0))),
                    'criteria_validation' => [
                        'consistent' => (bool) ($criteriaValidation['consistent'] ?? false),
                        'total_criteria_score' => max(0, min(100, (int) ($criteriaValidation['total_criteria_score'] ?? 0))),
                    ],
                    'feedback_validation' => [
                        'quality' => $feedbackQuality,
                        'semantic_consistency' => (bool) ($feedbackValidation['semantic_consistency'] ?? false),
                    ],
                    'confidence' => round(max(0, min(1, (float) ($item['confidence'] ?? 0))), 2),
                    'retry_count' => max(0, min(3, (int) ($item['retry_count'] ?? 0))),
                    'requires_manual_review' => (bool) ($item['requires_manual_review'] ?? false),
                    'warnings' => $this->normalizeStringList($item['warnings'] ?? []),
                    'final_feedback' => trim((string) ($item['final_feedback'] ?? '')),
                    'validation_status' => $validationStatus,
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
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,post_evaluation_validation_status:string,next_stage:string}
     */
    private function failureResult(Submission $submission, array $warnings): array
    {
        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => [],
            'warnings' => $warnings,
            'post_evaluation_validation_status' => 'failed',
            'next_stage' => 'result_finalization',
        ];
    }
}
