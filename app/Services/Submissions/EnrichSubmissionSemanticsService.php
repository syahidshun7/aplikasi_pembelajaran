<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class EnrichSubmissionSemanticsService
{
    /**
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,semantic_enrichment_status:string,next_stage:string}
     */
    public function enrich(Submission $submission): array
    {
        $payload = $this->buildPythonPayload($submission);
        $inputPath = $this->writePayloadFile($payload);

        try {
            $output = $this->runPythonEnricher($inputPath);
            $decoded = json_decode($output, true);

            if (! is_array($decoded)) {
                return $this->failureResult($submission, ['python_semantic_enricher_invalid_json']);
            }

            return $this->normalizeResult($submission, $decoded);
        } catch (ProcessFailedException|ProcessRuntimeException) {
            return $this->failureResult($submission, ['python_semantic_enricher_process_failed']);
        } catch (Throwable) {
            return $this->failureResult($submission, ['python_semantic_enricher_exception']);
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
        $structureResult = is_array($submission->structure_result) ? $submission->structure_result : [];
        $cleaningResult = is_array($submission->cleaning_result) ? $submission->cleaning_result : [];
        $items = is_array($submission->structured_items) ? $submission->structured_items : [];

        if ($items === [] && is_array($structureResult['items'] ?? null)) {
            $items = $structureResult['items'];
        }

        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => $items,
            'document_pattern' => (string) ($structureResult['document_pattern'] ?? 'mixed'),
            'language' => (string) ($cleaningResult['language'] ?? $submission->cleaning_language ?? 'unknown'),
            'structure_warnings' => is_array($structureResult['warnings'] ?? null) ? $structureResult['warnings'] : [],
            'max_items' => max(1, (int) config('services.ai.semantic_enrichment.max_items', 500)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayloadFile(array $payload): string
    {
        $directory = storage_path('app/tmp/submission-semantic-enrichment');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.(string) Str::uuid().'.json';
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function runPythonEnricher(string $inputPath): string
    {
        $pythonBinary = (string) config('services.ai.semantic_enrichment.python_binary', 'python');
        $scriptPath = base_path((string) config('services.ai.semantic_enrichment.script_path', 'scripts/submission_semantic_enricher.py'));
        $timeout = max(10, (int) config('services.ai.semantic_enrichment.timeout_seconds', 30));

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
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,semantic_enrichment_status:string,next_stage:string}
     */
    private function normalizeResult(Submission $submission, array $decoded): array
    {
        $items = $this->normalizeItems($decoded['items'] ?? []);
        $status = (string) ($decoded['semantic_enrichment_status'] ?? 'failed');

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
            'semantic_enrichment_status' => $status,
            'next_stage' => 'rubric_preparation',
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

                $language = strtolower(trim((string) ($item['language'] ?? 'other')));
                if (! in_array($language, ['id', 'en', 'other'], true)) {
                    $language = 'other';
                }

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

                $difficulty = strtolower(trim((string) ($item['difficulty'] ?? ($item['complexity'] ?? 'medium'))));
                if (! in_array($difficulty, ['low', 'medium', 'high'], true)) {
                    $difficulty = 'medium';
                }

                $answerLength = strtolower(trim((string) ($item['answer_length'] ?? 'empty')));
                if (! in_array($answerLength, ['empty', 'short', 'medium', 'long'], true)) {
                    $answerLength = 'empty';
                }

                $answerQuality = strtolower(trim((string) ($item['answer_quality'] ?? 'low_confidence')));
                if (! in_array($answerQuality, ['normal', 'low_confidence', 'spam_like', 'repetitive'], true)) {
                    $answerQuality = 'low_confidence';
                }

                $strategy = strtolower(trim((string) ($item['evaluation_strategy'] ?? 'semantic_similarity')));
                if ($strategy === 'rule_engine') {
                    $strategy = 'rule_based_evaluation';
                }
                if (! in_array($strategy, ['exact_match', 'semantic_similarity', 'deep_semantic_evaluation', 'ai_rubric_evaluation', 'rule_based_evaluation'], true)) {
                    $strategy = 'semantic_similarity';
                }

                $semanticTags = collect(is_array($item['semantic_tags'] ?? null) ? $item['semantic_tags'] : (is_array($item['tags'] ?? null) ? $item['tags'] : []))
                    ->map(fn ($tag) => strtolower(trim((string) $tag)))
                    ->filter(fn ($tag) => $tag !== '')
                    ->unique()
                    ->values()
                    ->all();

                $expectedConcepts = $this->normalizeExpectedConcepts($item['expected_concepts'] ?? []);

                $confidence = round(max(0, min(1, (float) ($item['confidence'] ?? 0))), 2);

                return [
                    'question_number' => $questionNumber,
                    'question' => trim((string) ($item['question'] ?? '')),
                    'answer' => trim((string) ($item['answer'] ?? '')),
                    'language' => $language,
                    'subject' => $subject,
                    'question_type' => $questionType,
                    'difficulty' => $difficulty,
                    'complexity' => $difficulty,
                    'answer_length' => $answerLength,
                    'answer_quality' => $answerQuality,
                    'evaluation_strategy' => $strategy,
                    'expected_concepts' => $expectedConcepts,
                    'semantic_tags' => $semanticTags,
                    'tags' => $semanticTags,
                    'confidence' => $confidence,
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

        $concepts = collect($value)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                $concept = trim((string) ($row['concept'] ?? ''));
                $weight = max(0.0, (float) ($row['weight'] ?? 0));

                return [
                    'concept' => $concept,
                    'weight' => $weight,
                ];
            })
            ->filter(fn (array $row) => $row['concept'] !== '')
            ->take(7)
            ->values();

        if ($concepts->isEmpty()) {
            return [];
        }

        $totalWeight = (float) $concepts->sum('weight');
        if ($totalWeight <= 0) {
            $equalWeight = round(1 / $concepts->count(), 3);
            $normalized = $concepts
                ->map(fn (array $row) => [
                    'concept' => $row['concept'],
                    'weight' => $equalWeight,
                ])
                ->values();
        } else {
            $normalized = $concepts
                ->map(fn (array $row) => [
                    'concept' => $row['concept'],
                    'weight' => round($row['weight'] / $totalWeight, 3),
                ])
                ->values();
        }

        $currentSum = (float) $normalized->sum('weight');
        $delta = round(1.0 - $currentSum, 3);
        if ($normalized->isNotEmpty() && abs($delta) > 0) {
            $first = $normalized->first();
            $first['weight'] = round(max(0.0, (float) $first['weight'] + $delta), 3);
            $normalized->put(0, $first);
        }

        return $normalized->all();
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
     * @return array{submission_id:string,items:array<int, array<string, mixed>>,warnings:array<int, string>,semantic_enrichment_status:string,next_stage:string}
     */
    private function failureResult(Submission $submission, array $warnings): array
    {
        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'items' => [],
            'warnings' => $warnings,
            'semantic_enrichment_status' => 'failed',
            'next_stage' => 'rubric_preparation',
        ];
    }
}
