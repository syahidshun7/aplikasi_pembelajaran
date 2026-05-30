<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PrepareSubmissionRubricService
{
    /**
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,rubric_preparation_status:string,next_stage:string}
     */
    public function prepare(Submission $submission): array
    {
        $payload = $this->buildPythonPayload($submission);
        $inputPath = $this->writePayloadFile($payload);

        try {
            $output = $this->runPythonPreparer($inputPath);
            $decoded = json_decode($output, true);

            if (! is_array($decoded)) {
                return $this->failureResult($submission, ['python_rubric_preparation_invalid_json']);
            }

            return $this->normalizeResult($submission, $decoded);
        } catch (ProcessFailedException|ProcessRuntimeException) {
            return $this->failureResult($submission, ['python_rubric_preparation_process_failed']);
        } catch (Throwable) {
            return $this->failureResult($submission, ['python_rubric_preparation_exception']);
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
        $semanticResult = is_array($submission->semantic_result) ? $submission->semantic_result : [];
        $items = is_array($submission->semantic_items) ? $submission->semantic_items : [];

        if ($items === [] && is_array($semanticResult['items'] ?? null)) {
            $items = $semanticResult['items'];
        }

        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => $items,
            'rubric' => $this->rubricContext($submission),
            'reference_answers' => $this->referenceAnswers($submission),
            'max_items' => max(1, (int) config('services.ai.rubric_preparation.max_items', 500)),
            'allowed_feedback_length' => max(50, (int) config('services.ai.rubric_preparation.allowed_feedback_length', 200)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rubricContext(Submission $submission): ?array
    {
        if (! $submission->exists || ! $submission->quest_id) {
            return null;
        }

        $submission->loadMissing([
            'quest.rubric:id,title,max_score',
            'quest.rubric.criteria:id,rubric_id,name,weight,order',
            'quest.taskBank:id,rubric_id',
            'quest.taskBank.rubric:id,title,max_score',
            'quest.taskBank.rubric.criteria:id,rubric_id,name,weight,order',
        ]);

        $rubric = $submission->quest?->rubric;
        if (! $rubric) {
            $rubric = $submission->quest?->taskBank?->rubric;
        }

        if (! $rubric) {
            return null;
        }

        $criteria = $rubric->criteria
            ->map(fn ($criterion) => [
                'name' => trim((string) $criterion->name),
                'weight' => (float) $criterion->weight,
            ])
            ->filter(fn (array $criterion) => $criterion['name'] !== '')
            ->values()
            ->all();

        return [
            'rubric_id' => (int) $rubric->id,
            'criteria' => $criteria,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function referenceAnswers(Submission $submission): array
    {
        if (! $submission->exists || ! $submission->quest_id) {
            return [];
        }

        $submission->loadMissing([
            'quest.taskBank.questions:id,task_bank_id,answer_key,sort_order,is_active',
        ]);

        $questions = $submission->quest?->taskBank?->questions;
        if (! $questions) {
            return [];
        }

        $output = [];
        foreach ($questions->sortBy('sort_order')->values() as $index => $question) {
            $answerKey = trim((string) ($question->answer_key ?? ''));
            if ($answerKey === '') {
                continue;
            }

            $questionNumber = (int) ($question->sort_order ?: $index + 1);
            if ($questionNumber <= 0) {
                continue;
            }

            $output[(string) $questionNumber] = $answerKey;
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayloadFile(array $payload): string
    {
        $directory = storage_path('app/tmp/submission-rubric-preparation');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.(string) Str::uuid().'.json';
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function runPythonPreparer(string $inputPath): string
    {
        $pythonBinary = (string) config('services.ai.rubric_preparation.python_binary', 'python');
        $scriptPath = base_path((string) config('services.ai.rubric_preparation.script_path', 'scripts/submission_rubric_preparer.py'));
        $timeout = max(10, (int) config('services.ai.rubric_preparation.timeout_seconds', 30));

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
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,rubric_preparation_status:string,next_stage:string}
     */
    private function normalizeResult(Submission $submission, array $decoded): array
    {
        $items = $this->normalizeItems($decoded['items'] ?? []);
        $status = (string) ($decoded['rubric_preparation_status'] ?? 'failed');

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
            'rubric_preparation_status' => $status,
            'next_stage' => 'ai_evaluation',
        ];
    }

    /**
     * @param  mixed  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item): array {
                $questionNumber = $item['question_number'] ?? null;
                $questionNumber = is_numeric($questionNumber) ? (int) $questionNumber : null;

                $subject = strtolower(trim((string) ($item['subject'] ?? 'other')));
                if (in_array($subject, ['other', 'general'], true)) {
                    $subject = 'software_engineering';
                }
                if (! in_array($subject, ['technology', 'database', 'backend', 'laravel', 'programming', 'web_development', 'software_engineering', 'mathematics', 'biology', 'chemistry', 'physics', 'language', 'history', 'economics'], true)) {
                    $subject = 'software_engineering';
                }

                $questionType = strtolower(trim((string) ($item['question_type'] ?? 'analysis')));
                if ($questionType === 'essay') {
                    $questionType = 'explanation';
                } elseif ($questionType === 'multiple_choice') {
                    $questionType = 'definition';
                }
                if (! in_array($questionType, ['definition', 'explanation', 'reasoning', 'comparison', 'calculation', 'implementation', 'analysis'], true)) {
                    $questionType = 'analysis';
                }

                $strategy = strtolower(trim((string) ($item['evaluation_strategy'] ?? 'semantic_similarity')));
                if ($strategy === 'rule_engine') {
                    $strategy = 'rule_based_evaluation';
                }
                if (! in_array($strategy, ['exact_match', 'semantic_similarity', 'deep_semantic_evaluation', 'ai_rubric_evaluation', 'rule_based_evaluation'], true)) {
                    $strategy = 'semantic_similarity';
                }

                $difficulty = strtolower(trim((string) ($item['difficulty'] ?? ($item['complexity'] ?? 'medium'))));
                if (! in_array($difficulty, ['low', 'medium', 'high'], true)) {
                    $difficulty = 'medium';
                }

                $expectedConcepts = $this->normalizeExpectedConcepts($item['expected_concepts'] ?? null);
                $semanticTags = $this->normalizeSemanticTags($item['semantic_tags'] ?? ($item['tags'] ?? null), $expectedConcepts);

                $selectedRubric = is_array($item['selected_rubric'] ?? null) ? $item['selected_rubric'] : [];
                $rawCriteria = is_array($selectedRubric['criteria'] ?? null) ? $selectedRubric['criteria'] : [];
                $criteria = collect($rawCriteria)
                    ->filter(fn ($criterion) => is_array($criterion))
                    ->map(function (array $criterion): array {
                        return [
                            'name' => trim((string) ($criterion['name'] ?? '')),
                            'weight' => max(0, (int) ($criterion['weight'] ?? 0)),
                        ];
                    })
                    ->filter(fn (array $criterion) => $criterion['name'] !== '')
                    ->values()
                    ->all();

                $constraints = is_array($item['constraints'] ?? null) ? $item['constraints'] : [];
                $scoreRange = is_array($constraints['score_range'] ?? null) ? $constraints['score_range'] : [0, 100];
                $scoreMin = is_numeric($scoreRange[0] ?? null) ? (int) $scoreRange[0] : 0;
                $scoreMax = is_numeric($scoreRange[1] ?? null) ? (int) $scoreRange[1] : 100;
                if ($scoreMin > $scoreMax) {
                    [$scoreMin, $scoreMax] = [$scoreMax, $scoreMin];
                }

                $payloadStatus = strtolower(trim((string) ($item['payload_status'] ?? 'failed')));
                if (! in_array($payloadStatus, ['ready', 'partial', 'failed'], true)) {
                    $payloadStatus = 'failed';
                }

                $rawPayload = is_array($item['evaluation_payload'] ?? null) ? $item['evaluation_payload'] : [];
                $payloadQuestion = trim((string) ($rawPayload['question'] ?? ($item['question'] ?? '')));
                $payloadStudentAnswer = trim((string) ($rawPayload['student_answer'] ?? ($item['student_answer'] ?? ($item['answer'] ?? ''))));
                $payloadReferenceAnswer = ($rawPayload['reference_answer'] ?? ($item['reference_answer'] ?? null)) !== null
                    ? trim((string) ($rawPayload['reference_answer'] ?? ($item['reference_answer'] ?? '')))
                    : null;

                $rawPayloadExpectedConcepts = $this->normalizeExpectedConcepts($rawPayload['expected_concepts'] ?? null);
                $payloadExpectedConcepts = $rawPayloadExpectedConcepts !== [] ? $rawPayloadExpectedConcepts : $expectedConcepts;

                $rawPayloadSemanticTags = $rawPayload['semantic_tags'] ?? ($rawPayload['tags'] ?? null);
                $payloadSemanticTags = $this->normalizeSemanticTags($rawPayloadSemanticTags, $payloadExpectedConcepts);
                if ($payloadSemanticTags === []) {
                    $payloadSemanticTags = $semanticTags;
                }

                return [
                    'question_number' => $questionNumber,
                    'question' => trim((string) ($item['question'] ?? '')),
                    'student_answer' => trim((string) ($item['student_answer'] ?? '')),
                    'reference_answer' => ($item['reference_answer'] ?? null) !== null
                        ? trim((string) ($item['reference_answer'] ?? ''))
                        : null,
                    'subject' => $subject,
                    'question_type' => $questionType,
                    'difficulty' => $difficulty,
                    'evaluation_strategy' => $strategy,
                    'expected_concepts' => $expectedConcepts,
                    'semantic_tags' => $semanticTags,
                    'selected_rubric' => [
                        'rubric_id' => $selectedRubric['rubric_id'] ?? null,
                        'criteria' => $criteria,
                    ],
                    'constraints' => [
                        'score_range' => [$scoreMin, $scoreMax],
                        'allowed_feedback_length' => max(50, (int) ($constraints['allowed_feedback_length'] ?? 200)),
                        'strict_json_output' => (bool) ($constraints['strict_json_output'] ?? true),
                        'no_extra_explanation' => (bool) ($constraints['no_extra_explanation'] ?? true),
                    ],
                    'evaluation_payload' => [
                        'question' => $payloadQuestion,
                        'student_answer' => $payloadStudentAnswer,
                        'reference_answer' => $payloadReferenceAnswer,
                        'subject' => $subject,
                        'question_type' => $questionType,
                        'difficulty' => $difficulty,
                        'evaluation_strategy' => $strategy,
                        'expected_concepts' => $payloadExpectedConcepts,
                        'semantic_tags' => $payloadSemanticTags,
                        'rubric' => [
                            'criteria' => $criteria,
                        ],
                        'constraints' => [
                            'score_range' => [$scoreMin, $scoreMax],
                            'allowed_feedback_length' => max(50, (int) ($constraints['allowed_feedback_length'] ?? 200)),
                            'strict_json_output' => (bool) ($constraints['strict_json_output'] ?? true),
                            'no_extra_explanation' => (bool) ($constraints['no_extra_explanation'] ?? true),
                        ],
                    ],
                    'payload_status' => $payloadStatus,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $value
     * @return array<int, array{concept:string,weight:float}>
     */
    private function normalizeExpectedConcepts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = collect($value)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item): array {
                $concept = strtolower(trim((string) ($item['concept'] ?? '')));
                $weight = is_numeric($item['weight'] ?? null) ? (float) $item['weight'] : 0.0;

                return [
                    'concept' => $concept,
                    'weight' => max(0.0, $weight),
                ];
            })
            ->filter(fn (array $item) => $item['concept'] !== '')
            ->take(7)
            ->values()
            ->all();

        if ($rows === []) {
            return [];
        }

        $total = array_sum(array_map(fn (array $item) => (float) $item['weight'], $rows));

        if ($total <= 0) {
            $uniformWeight = round(1 / count($rows), 3);
            $normalized = array_map(fn (array $item) => [
                'concept' => $item['concept'],
                'weight' => (float) $uniformWeight,
            ], $rows);
        } else {
            $normalized = array_map(fn (array $item) => [
                'concept' => $item['concept'],
                'weight' => (float) round(((float) $item['weight']) / $total, 3),
            ], $rows);
        }

        $sum = array_sum(array_map(fn (array $item) => (float) $item['weight'], $normalized));
        $delta = round(1.0 - $sum, 3);
        $normalized[0]['weight'] = (float) round(max(0.0, min(1.0, ((float) $normalized[0]['weight']) + $delta)), 3);

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @param  array<int, array{concept:string,weight:float}>  $expectedConcepts
     * @return array<int, string>
     */
    private function normalizeSemanticTags(mixed $value, array $expectedConcepts): array
    {
        $tags = [];
        if (is_array($value)) {
            foreach ($value as $tag) {
                $normalized = $this->normalizeTag((string) $tag);
                if ($normalized !== '') {
                    $tags[] = $normalized;
                }
            }
        }

        if ($tags === []) {
            foreach ($expectedConcepts as $conceptRow) {
                $normalized = $this->normalizeTag((string) ($conceptRow['concept'] ?? ''));
                if ($normalized !== '') {
                    $tags[] = $normalized;
                }
            }
        }

        return collect($tags)
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function normalizeTag(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        return Str::limit($normalized, 40, '');
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
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,rubric_preparation_status:string,next_stage:string}
     */
    private function failureResult(Submission $submission, array $warnings): array
    {
        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => [],
            'warnings' => $warnings,
            'rubric_preparation_status' => 'failed',
            'next_stage' => 'ai_evaluation',
        ];
    }
}
