<?php

namespace App\Services\Ai;

use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class SubmissionArtifactExtractorService
{
    public function __construct(
        private readonly PdfParser $pdfParser,
    ) {
    }

    /**
     * @return array{
     *   combined_text:string,
     *   source_flags: array<int, string>,
     *   warnings: array<int, string>
     * }
     */
    public function extract(Submission $submission): array
    {
        $submissionText = trim((string) ($submission->content ?? ''));
        $storedPath = trim((string) ($submission->file_path ?? ''));

        $segments = [];
        $sourceFlags = [];
        $warnings = [];

        if ($submissionText !== '') {
            $segments[] = "[TEXT_SUBMISSION]\n".$submissionText;
            $sourceFlags[] = 'text';
        }

        if ($storedPath !== '') {
            $disk = Storage::disk('public');

            if (! $disk->exists($storedPath)) {
                $warnings[] = 'FILE_NOT_FOUND_ON_STORAGE';
            } else {
                $extension = strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION));
                $absolutePath = $disk->path($storedPath);

                if ($extension === 'pdf') {
                    if ((bool) config('services.ai.pdf_extraction_enabled', true)) {
                        $pdfText = $this->extractPdfText($absolutePath);
                        if ($pdfText !== '') {
                            $segments[] = "[PDF_EXTRACTED_TEXT]\n".$pdfText;
                            $sourceFlags[] = 'pdf';
                        } else {
                            $warnings[] = 'PDF_TEXT_EMPTY_OR_UNREADABLE';
                        }
                    }
                } elseif (in_array($extension, ['txt', 'md', 'log', 'csv'], true)) {
                    $plain = trim((string) @file_get_contents($absolutePath));
                    if ($plain !== '') {
                        $segments[] = "[FILE_TEXT_EXTRACT]\n".$plain;
                        $sourceFlags[] = 'file_text';
                    }
                }
            }
        }

        $combinedText = trim(implode("\n\n---\n\n", $segments));
        $maxChars = max(2000, (int) config('services.ai.artifact_max_chars', 12000));

        if ($combinedText === '') {
            $combinedText = '[NO_READABLE_ARTIFACT_FOUND]';
            $warnings[] = 'NO_TEXT_SOURCE_DETECTED';
        }

        if (mb_strlen($combinedText) > $maxChars) {
            $combinedText = mb_substr($combinedText, 0, $maxChars);
            $warnings[] = 'ARTIFACT_TRUNCATED';
        }

        return [
            'combined_text' => $combinedText,
            'source_flags' => array_values(array_unique($sourceFlags)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function extractPdfText(string $absolutePath): string
    {
        try {
            $pdf = $this->pdfParser->parseFile($absolutePath);
            $text = trim((string) $pdf->getText());

            $normalized = str_replace(["\r\n", "\r"], "\n", $text);
            $normalized = preg_replace('/[ \t\x0B\f\x{00A0}]+/u', ' ', $normalized) ?? $normalized;
            $normalized = preg_replace('/\n{3,}/u', "\n\n", $normalized) ?? $normalized;

            return trim($normalized);
        } catch (Throwable) {
            return '';
        }
    }
}
