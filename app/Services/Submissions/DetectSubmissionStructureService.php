<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DetectSubmissionStructureService
{
    /**
     * @return array{submission_id:string,document_pattern:string,items:array<int, array<string, mixed>>,instruction_blocks:array<int, string>,warnings:array<int, string>,structure_detection_status:string,next_stage:string}
     */
    public function detect(Submission $submission): array
    {
        $payload = $this->buildPythonPayload($submission);
        $inputPath = $this->writePayloadFile($payload);

        try {
            $output = $this->runPythonDetector($inputPath);
            $decoded = json_decode($output, true);

            if (! is_array($decoded)) {
                return $this->failureResult($submission, ['python_structure_detector_invalid_json']);
            }

            return $this->normalizeResult($submission, $decoded);
        } catch (ProcessFailedException|ProcessRuntimeException) {
            return $this->failureResult($submission, ['python_structure_detector_process_failed']);
        } catch (Throwable) {
            return $this->failureResult($submission, ['python_structure_detector_exception']);
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
        $cleaningResult = is_array($submission->cleaning_result) ? $submission->cleaning_result : [];
        $extractionResult = is_array($submission->extraction_result) ? $submission->extraction_result : [];

        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'clean_text' => (string) ($submission->clean_text ?: ($cleaningResult['clean_text'] ?? '')),
            'language' => (string) ($cleaningResult['language'] ?? $submission->cleaning_language ?? 'unknown'),
            'detected_content_type' => (string) ($extractionResult['detected_content_type'] ?? 'unknown'),
            'cleaning_status' => (string) ($cleaningResult['cleaning_status'] ?? ''),
            'cleaning_warnings' => is_array($cleaningResult['warnings'] ?? null) ? $cleaningResult['warnings'] : [],
            'task_questions' => $this->taskQuestions($submission),
            'max_chars' => max(1000, (int) config('services.ai.structure_detection.max_chars', 200000)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayloadFile(array $payload): string
    {
        $directory = storage_path('app/tmp/submission-structure-detection');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.(string) Str::uuid().'.json';
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function runPythonDetector(string $inputPath): string
    {
        $pythonBinary = (string) config('services.ai.structure_detection.python_binary', 'python');
        $scriptPath = base_path((string) config('services.ai.structure_detection.script_path', 'scripts/submission_structure_detector.py'));
        $timeout = max(10, (int) config('services.ai.structure_detection.timeout_seconds', 30));

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
     * @return array<int, array<string, mixed>>
     */
    private function taskQuestions(Submission $submission): array
    {
        if (! $submission->exists || ! $submission->quest_id) {
            return [];
        }

        $submission->loadMissing('quest.taskBank.questions');
        $questions = $submission->quest?->taskBank?->questions;

        if (! $questions) {
            return [];
        }

        return $questions
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($question, int $index) => [
                'uuid' => (string) $question->uuid,
                'question_number' => (int) ($question->sort_order ?: $index + 1),
                'question' => (string) $question->question_text,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{submission_id:string,document_pattern:string,items:array<int, array<string, mixed>>,instruction_blocks:array<int, string>,warnings:array<int, string>,structure_detection_status:string,next_stage:string}
     */
    private function normalizeResult(Submission $submission, array $decoded): array
    {
        $items = $this->normalizeItems($decoded['items'] ?? []);
        $status = (string) ($decoded['structure_detection_status'] ?? 'failed');

        if (! in_array($status, ['success', 'partial', 'failed'], true)) {
            $status = 'failed';
        }
        if ($items === []) {
            $status = 'failed';
        }

        return [
            'submission_id' => (string) (($decoded['submission_id'] ?? null) ?: $submission->submission_id ?: $submission->uuid ?: $submission->id),
            'document_pattern' => $this->normalizeDocumentPattern((string) ($decoded['document_pattern'] ?? 'mixed')),
            'items' => $items,
            'instruction_blocks' => $this->normalizeStringList($decoded['instruction_blocks'] ?? []),
            'warnings' => $this->normalizeStringList($decoded['warnings'] ?? []),
            'structure_detection_status' => $status,
            'next_stage' => 'semantic_enrichment',
        ];
    }

    private function normalizeDocumentPattern(string $pattern): string
    {
        $pattern = strtolower(trim($pattern));

        return in_array($pattern, ['numbered_list', 'qa_format', 'essay_block', 'mixed'], true) ? $pattern : 'mixed';
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
                $question = isset($item['question']) ? trim((string) $item['question']) : null;
                $answer = trim((string) ($item['answer'] ?? ''));
                $isEmpty = (bool) ($item['is_empty'] ?? $answer === '');
                $answerStatus = (string) ($item['answer_status'] ?? ($isEmpty ? 'empty' : 'filled'));

                if (! in_array($answerStatus, ['filled', 'empty', 'unclear'], true)) {
                    $answerStatus = $isEmpty ? 'empty' : 'unclear';
                }
                if ($isEmpty) {
                    $answerStatus = 'empty';
                }

                return [
                    'question_number' => $questionNumber,
                    'question' => $question !== '' ? $question : null,
                    'answer' => $answer,
                    'answer_status' => $answerStatus,
                    'is_empty' => $isEmpty,
                ];
            })
            ->values()
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
     * @return array{submission_id:string,document_pattern:string,items:array<int, array<string, mixed>>,instruction_blocks:array<int, string>,warnings:array<int, string>,structure_detection_status:string,next_stage:string}
     */
    private function failureResult(Submission $submission, array $warnings): array
    {
        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'document_pattern' => 'mixed',
            'items' => [],
            'instruction_blocks' => [],
            'warnings' => $warnings,
            'structure_detection_status' => 'failed',
            'next_stage' => 'semantic_enrichment',
        ];
    }
}
