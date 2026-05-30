<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class CleanSubmissionTextService
{
    /**
     * @return array{submission_id:string,clean_text:string,language:string,cleaning_status:string,changes_summary:array<string, int>,warnings:array<int, string>,next_stage:string}
     */
    public function clean(Submission $submission): array
    {
        $payload = $this->buildPythonPayload($submission);
        $inputPath = $this->writePayloadFile($payload);

        try {
            $output = $this->runPythonCleaner($inputPath);
            $decoded = json_decode($output, true);

            if (! is_array($decoded)) {
                return $this->failureResult($submission, ['python_cleaner_invalid_json']);
            }

            return $this->normalizeResult($submission, $decoded);
        } catch (ProcessFailedException|ProcessRuntimeException) {
            return $this->failureResult($submission, ['python_cleaner_process_failed']);
        } catch (Throwable) {
            return $this->failureResult($submission, ['python_cleaner_exception']);
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
        $extractionResult = is_array($submission->extraction_result) ? $submission->extraction_result : [];

        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'raw_text' => (string) ($submission->extracted_text ?: ($extractionResult['raw_text'] ?? '')),
            'detected_content_type' => (string) ($extractionResult['detected_content_type'] ?? 'unknown'),
            'extraction_warnings' => is_array($extractionResult['warnings'] ?? null) ? $extractionResult['warnings'] : [],
            'ocr_confidence' => $extractionResult['ocr_confidence'] ?? null,
            'max_chars' => max(1000, (int) config('services.ai.cleaning.max_chars', 200000)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayloadFile(array $payload): string
    {
        $directory = storage_path('app/tmp/submission-cleaning');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.(string) Str::uuid().'.json';
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function runPythonCleaner(string $inputPath): string
    {
        $pythonBinary = (string) config('services.ai.cleaning.python_binary', 'python');
        $scriptPath = base_path((string) config('services.ai.cleaning.script_path', 'scripts/submission_cleaner.py'));
        $timeout = max(10, (int) config('services.ai.cleaning.timeout_seconds', 30));

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
     * @return array{submission_id:string,clean_text:string,language:string,cleaning_status:string,changes_summary:array<string, int>,warnings:array<int, string>,next_stage:string}
     */
    private function normalizeResult(Submission $submission, array $decoded): array
    {
        $cleanText = trim((string) ($decoded['clean_text'] ?? ''));
        $status = (string) ($decoded['cleaning_status'] ?? 'failed');
        if (! in_array($status, ['success', 'partial', 'failed'], true)) {
            $status = 'failed';
        }
        if ($cleanText === '') {
            $status = 'failed';
        }

        return [
            'submission_id' => (string) ($decoded['submission_id'] ?? $submission->submission_id ?: $submission->uuid ?: $submission->id),
            'clean_text' => $cleanText,
            'language' => $this->normalizeLanguage((string) ($decoded['language'] ?? 'unknown')),
            'cleaning_status' => $status,
            'changes_summary' => $this->normalizeChanges($decoded['changes_summary'] ?? []),
            'warnings' => $this->normalizeWarnings($decoded['warnings'] ?? []),
            'next_stage' => 'structure_detection',
        ];
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        return in_array($language, ['id', 'en', 'unknown'], true) ? $language : 'unknown';
    }

    /**
     * @param  mixed  $changes
     * @return array<string, int>
     */
    private function normalizeChanges(mixed $changes): array
    {
        $changes = is_array($changes) ? $changes : [];

        return [
            'noise_removed' => max(0, (int) ($changes['noise_removed'] ?? 0)),
            'ocr_corrections' => max(0, (int) ($changes['ocr_corrections'] ?? 0)),
            'line_break_fixed' => max(0, (int) ($changes['line_break_fixed'] ?? 0)),
            'garbage_removed' => max(0, (int) ($changes['garbage_removed'] ?? 0)),
        ];
    }

    /**
     * @param  mixed  $warnings
     * @return array<int, string>
     */
    private function normalizeWarnings(mixed $warnings): array
    {
        if (! is_array($warnings)) {
            return [];
        }

        return collect($warnings)
            ->map(fn ($warning) => trim((string) $warning))
            ->filter(fn ($warning) => $warning !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array{submission_id:string,clean_text:string,language:string,cleaning_status:string,changes_summary:array<string, int>,warnings:array<int, string>,next_stage:string}
     */
    private function failureResult(Submission $submission, array $warnings): array
    {
        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'clean_text' => '',
            'language' => 'unknown',
            'cleaning_status' => 'failed',
            'changes_summary' => [
                'noise_removed' => 0,
                'ocr_corrections' => 0,
                'line_break_fixed' => 0,
                'garbage_removed' => 0,
            ],
            'warnings' => $warnings,
            'next_stage' => 'structure_detection',
        ];
    }
}
