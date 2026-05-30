<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PresentSubmissionResultService
{
    /**
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,result_presentation_status:string,next_stage:string}
     */
    public function present(Submission $submission): array
    {
        $payload = $this->buildPythonPayload($submission);
        $inputPath = $this->writePayloadFile($payload);

        try {
            $output = $this->runPythonPresenter($inputPath);
            $decoded = json_decode($output, true);

            if (! is_array($decoded)) {
                return $this->failureResult($submission, ['python_result_presenter_invalid_json']);
            }

            return $this->normalizeResult($submission, $decoded, $payload['source_items']);
        } catch (ProcessFailedException|ProcessRuntimeException) {
            return $this->failureResult($submission, ['python_result_presenter_process_failed']);
        } catch (Throwable) {
            return $this->failureResult($submission, ['python_result_presenter_exception']);
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
        $validationResult = is_array($submission->post_evaluation_validation_result) ? $submission->post_evaluation_validation_result : [];
        $validatedItems = is_array($submission->post_evaluation_validation_items) ? $submission->post_evaluation_validation_items : [];
        if ($validatedItems === [] && is_array($validationResult['items'] ?? null)) {
            $validatedItems = $validationResult['items'];
        }

        $aiEvaluationResult = is_array($submission->ai_evaluation_result) ? $submission->ai_evaluation_result : [];
        $aiItems = is_array($submission->ai_evaluation_items) ? $submission->ai_evaluation_items : [];
        if ($aiItems === [] && is_array($aiEvaluationResult['items'] ?? null)) {
            $aiItems = $aiEvaluationResult['items'];
        }

        $semanticResult = is_array($submission->semantic_result) ? $submission->semantic_result : [];
        $semanticItems = is_array($submission->semantic_items) ? $submission->semantic_items : [];
        if ($semanticItems === [] && is_array($semanticResult['items'] ?? null)) {
            $semanticItems = $semanticResult['items'];
        }

        $payloadItems = [];

        foreach ($validatedItems as $index => $validatedItem) {
            if (! is_array($validatedItem)) {
                continue;
            }

            $aiItem = is_array($aiItems[$index] ?? null) ? $aiItems[$index] : [];
            $semanticItem = is_array($semanticItems[$index] ?? null) ? $semanticItems[$index] : [];

            $payloadItems[] = [
                'question_number' => $validatedItem['question_number'] ?? null,
                'question' => (string) ($validatedItem['question'] ?? ''),
                'student_answer' => (string) ($validatedItem['student_answer'] ?? ''),
                'reference_answer' => ($validatedItem['reference_answer'] ?? null) !== null
                    ? (string) ($validatedItem['reference_answer'] ?? '')
                    : null,
                'subject' => (string) ($validatedItem['subject'] ?? ($semanticItem['subject'] ?? 'other')),
                'question_type' => (string) ($validatedItem['question_type'] ?? ($semanticItem['question_type'] ?? 'essay')),
                'complexity' => (string) ($semanticItem['complexity'] ?? 'medium'),
                'tags' => is_array($semanticItem['tags'] ?? null) ? $semanticItem['tags'] : [],
                'score' => $validatedItem['score'] ?? 0,
                'final_score' => $validatedItem['final_score'] ?? ($validatedItem['score'] ?? 0),
                'normalized_score' => $validatedItem['normalized_score'] ?? ($validatedItem['score'] ?? 0),
                'confidence' => $validatedItem['confidence'] ?? ($aiItem['evaluation_confidence'] ?? 0),
                'requires_manual_review' => (bool) ($validatedItem['requires_manual_review'] ?? false),
                'retry_count' => (int) ($validatedItem['retry_count'] ?? 0),
                'final_feedback' => (string) ($validatedItem['final_feedback'] ?? ($aiItem['feedback'] ?? '')),
                'feedback' => (string) ($aiItem['feedback'] ?? ''),
                'criteria_scores' => is_array($aiItem['criteria_scores'] ?? null) ? $aiItem['criteria_scores'] : [],
                'strengths' => is_array($aiItem['strengths'] ?? null) ? $aiItem['strengths'] : [],
                'weaknesses' => is_array($aiItem['weaknesses'] ?? null) ? $aiItem['weaknesses'] : [],
                'ai_version' => 'pipeline_v1',
            ];
        }

        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => $payloadItems,
            'source_items' => $payloadItems,
            'max_items' => max(1, (int) config('services.ai.result_presentation.max_items', 500)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayloadFile(array $payload): string
    {
        $directory = storage_path('app/tmp/submission-result-presentation');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.(string) Str::uuid().'.json';
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function runPythonPresenter(string $inputPath): string
    {
        $pythonBinary = (string) config('services.ai.result_presentation.python_binary', 'python');
        $scriptPath = base_path((string) config('services.ai.result_presentation.script_path', 'scripts/submission_result_presenter.py'));
        $timeout = max(10, (int) config('services.ai.result_presentation.timeout_seconds', 30));

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
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,result_presentation_status:string,next_stage:string}
     */
    private function normalizeResult(Submission $submission, array $decoded, array $sourceItems): array
    {
        $items = $this->normalizeItems($decoded['items'] ?? [], $sourceItems);
        $status = (string) ($decoded['result_presentation_status'] ?? 'failed');

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
            'result_presentation_status' => $status,
            'next_stage' => 'mentor_verdict',
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
                $mentorView = is_array($item['mentor_view'] ?? null) ? $item['mentor_view'] : [];
                $confidenceDisplay = is_array($item['confidence_display'] ?? null) ? $item['confidence_display'] : [];
                $analytics = is_array($item['analytics'] ?? null) ? $item['analytics'] : [];
                $historyRecord = is_array($item['history_record'] ?? null) ? $item['history_record'] : [];
                $notification = is_array($item['notification'] ?? null) ? $item['notification'] : [];

                $presentationStatus = strtolower(trim((string) ($item['presentation_status'] ?? 'failed')));
                if (! in_array($presentationStatus, ['success', 'partial', 'failed'], true)) {
                    $presentationStatus = 'failed';
                }

                $scoreLabel = trim((string) ($mentorView['score_label'] ?? 'Poor'));
                if (! in_array($scoreLabel, ['Excellent', 'Good', 'Fair', 'Poor'], true)) {
                    $scoreLabel = 'Poor';
                }

                $confidenceLevel = strtolower(trim((string) ($confidenceDisplay['level'] ?? 'low')));
                if (! in_array($confidenceLevel, ['high', 'medium', 'low'], true)) {
                    $confidenceLevel = 'low';
                }

                $difficultyLevel = strtolower(trim((string) ($analytics['difficulty_level'] ?? 'medium')));
                if (! in_array($difficultyLevel, ['low', 'medium', 'high'], true)) {
                    $difficultyLevel = 'medium';
                }

                $criteriaBreakdown = collect(is_array($mentorView['criteria_breakdown'] ?? null) ? $mentorView['criteria_breakdown'] : [])
                    ->filter(fn ($row) => is_array($row))
                    ->map(function (array $row): array {
                        return [
                            'name' => trim((string) ($row['name'] ?? '')),
                            'score' => max(0, min(100, (int) ($row['score'] ?? 0))),
                        ];
                    })
                    ->filter(fn (array $row) => $row['name'] !== '')
                    ->values()
                    ->all();

                return [
                    'question_number' => is_numeric($source['question_number'] ?? null) ? (int) $source['question_number'] : null,
                    'question' => trim((string) ($source['question'] ?? '')),
                    'student_answer' => trim((string) ($source['student_answer'] ?? '')),
                    'reference_answer' => ($source['reference_answer'] ?? null) !== null
                        ? trim((string) ($source['reference_answer'] ?? ''))
                        : null,
                    'subject' => strtolower(trim((string) ($source['subject'] ?? 'other'))),
                    'question_type' => strtolower(trim((string) ($source['question_type'] ?? 'essay'))),
                    'presentation_status' => $presentationStatus,
                    'submission_status' => 'evaluated',
                    'mentor_view' => [
                        'final_score' => max(0, min(100, (int) ($mentorView['final_score'] ?? 0))),
                        'score_label' => $scoreLabel,
                        'feedback_summary' => trim((string) ($mentorView['feedback_summary'] ?? '')),
                        'strengths' => $this->normalizeStringList($mentorView['strengths'] ?? []),
                        'improvements' => $this->normalizeStringList($mentorView['improvements'] ?? []),
                        'criteria_breakdown' => $criteriaBreakdown,
                    ],
                    'confidence_display' => [
                        'value' => round(max(0, min(1, (float) ($confidenceDisplay['value'] ?? 0))), 2),
                        'level' => $confidenceLevel,
                        'requires_manual_review' => (bool) ($confidenceDisplay['requires_manual_review'] ?? false),
                    ],
                    'analytics' => [
                        'difficulty_level' => $difficultyLevel,
                        'common_mistakes' => $this->normalizeStringList($analytics['common_mistakes'] ?? []),
                        'learning_tags' => $this->normalizeStringList($analytics['learning_tags'] ?? []),
                    ],
                    'history_record' => [
                        'saved' => (bool) ($historyRecord['saved'] ?? false),
                    ],
                    'export_options' => $this->normalizeExportOptions($item['export_options'] ?? []),
                    'notification' => [
                        'enabled' => (bool) ($notification['enabled'] ?? true),
                        'message' => trim((string) ($notification['message'] ?? '')),
                    ],
                    'warnings' => $this->normalizeStringList($item['warnings'] ?? []),
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
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeExportOptions(mixed $value): array
    {
        $allowed = ['pdf', 'excel', 'json'];

        if (! is_array($value)) {
            return $allowed;
        }

        $normalized = collect($value)
            ->map(fn ($item) => strtolower(trim((string) $item)))
            ->filter(fn ($item) => in_array($item, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? $allowed : $normalized;
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,result_presentation_status:string,next_stage:string}
     */
    private function failureResult(Submission $submission, array $warnings): array
    {
        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => [],
            'warnings' => $warnings,
            'result_presentation_status' => 'failed',
            'next_stage' => 'mentor_verdict',
        ];
    }
}
