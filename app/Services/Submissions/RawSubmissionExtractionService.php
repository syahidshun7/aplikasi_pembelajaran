<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class RawSubmissionExtractionService
{
    /**
     * @return array{submission_id:string,detected_content_type:string,extraction_method:string,raw_text:string,page_count:int,ocr_used:bool,ocr_confidence:float|null,extraction_status:string,warnings:array<int, string>}
     */
    public function extract(Submission $submission): array
    {
        $payload = $this->buildPythonPayload($submission);
        $inputPath = $this->writePayloadFile($payload);

        try {
            $output = $this->runPythonExtractor($inputPath);
            $decoded = json_decode($output, true);

            if (! is_array($decoded)) {
                return $this->failureResult($submission, ['python_extractor_invalid_json']);
            }

            return $this->normalizeResult($submission, $decoded);
        } catch (ProcessFailedException|ProcessRuntimeException) {
            return $this->failureResult($submission, ['python_extractor_process_failed']);
        } catch (Throwable) {
            return $this->failureResult($submission, ['python_extractor_exception']);
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
        $storedPath = trim((string) ($submission->file_path ?? ''));
        $absolutePath = '';
        $extension = '';

        if ($storedPath !== '') {
            $disk = Storage::disk('public');
            $absolutePath = $disk->path($storedPath);
            $extension = strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION));
        }

        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'content' => (string) ($submission->content ?? ''),
            'file_path' => $absolutePath,
            'file_extension' => $extension,
            'task_answers' => $this->taskAnswers($submission),
            'max_chars' => max(1000, (int) config('services.ai.extraction.max_chars', 200000)),
            'ocr_timeout_seconds' => max(10, (int) config('services.ai.extraction.ocr_timeout_seconds', 60)),
            'tesseract_binary' => (string) config('services.ai.extraction.tesseract_binary', 'tesseract'),
            'pdftoppm_binary' => (string) config('services.ai.extraction.pdftoppm_binary', 'pdftoppm'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayloadFile(array $payload): string
    {
        $directory = storage_path('app/tmp/submission-extraction');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.(string) Str::uuid().'.json';
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function runPythonExtractor(string $inputPath): string
    {
        $pythonBinary = (string) config('services.ai.extraction.python_binary', 'python');
        $scriptPath = base_path((string) config('services.ai.extraction.script_path', 'scripts/submission_extractor.py'));
        $timeout = max(15, (int) config('services.ai.extraction.ocr_timeout_seconds', 60) + 15);

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
     * @return array<string, mixed>
     */
    private function taskAnswers(Submission $submission): array
    {
        $scoresDetail = is_array($submission->scores_detail) ? $submission->scores_detail : [];
        $answers = $scoresDetail['answers'] ?? [];

        return is_array($answers) ? $answers : [];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{submission_id:string,detected_content_type:string,extraction_method:string,raw_text:string,page_count:int,ocr_used:bool,ocr_confidence:float|null,extraction_status:string,warnings:array<int, string>}
     */
    private function normalizeResult(Submission $submission, array $decoded): array
    {
        $ocrUsed = (bool) ($decoded['ocr_used'] ?? false);
        $rawText = trim((string) ($decoded['raw_text'] ?? ''));
        $status = (string) ($decoded['extraction_status'] ?? 'failed');

        if ($status !== 'success' || $rawText === '') {
            $status = 'failed';
        }

        return [
            'submission_id' => (string) ($decoded['submission_id'] ?? $submission->submission_id ?: $submission->uuid ?: $submission->id),
            'detected_content_type' => $this->allowedValue((string) ($decoded['detected_content_type'] ?? 'txt'), ['pdf_text', 'scan_pdf', 'image', 'docx', 'txt'], 'txt'),
            'extraction_method' => $this->allowedValue((string) ($decoded['extraction_method'] ?? 'txt_reader'), ['pdf_text', 'OCR', 'docx_parser', 'txt_reader'], 'txt_reader'),
            'raw_text' => $rawText,
            'page_count' => max(0, (int) ($decoded['page_count'] ?? 0)),
            'ocr_used' => $ocrUsed,
            'ocr_confidence' => $ocrUsed && isset($decoded['ocr_confidence']) ? max(0.0, min(1.0, (float) $decoded['ocr_confidence'])) : null,
            'extraction_status' => $status,
            'warnings' => $this->normalizeWarnings($decoded['warnings'] ?? []),
        ];
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedValue(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
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
     * @return array{submission_id:string,detected_content_type:string,extraction_method:string,raw_text:string,page_count:int,ocr_used:bool,ocr_confidence:float|null,extraction_status:string,warnings:array<int, string>}
     */
    private function failureResult(Submission $submission, array $warnings): array
    {
        return [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid ?: $submission->id),
            'detected_content_type' => $this->failureDetectedContentType($submission),
            'extraction_method' => 'txt_reader',
            'raw_text' => '',
            'page_count' => 0,
            'ocr_used' => false,
            'ocr_confidence' => null,
            'extraction_status' => 'failed',
            'warnings' => $warnings,
        ];
    }

    private function failureDetectedContentType(Submission $submission): string
    {
        return match ((string) $submission->file_type) {
            'pdf' => 'pdf_text',
            'image' => 'image',
            'docx' => 'docx',
            default => 'txt',
        };
    }
}
