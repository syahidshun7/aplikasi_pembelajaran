<?php

use App\Models\Submission;
use App\Services\Submissions\RawSubmissionExtractionService;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

test('raw submission extraction reads text submission as txt reader output', function () {
    $service = new RawSubmissionExtractionService();

    $submission = new Submission([
        'submission_id' => 'SUB-UNIT-001',
        'content' => "1. Apa itu internet?\nInternet adalah jaringan global.",
    ]);

    $result = $service->extract($submission);

    expect($result)->toMatchArray([
        'submission_id' => 'SUB-UNIT-001',
        'detected_content_type' => 'txt',
        'extraction_method' => 'txt_reader',
        'raw_text' => "1. Apa itu internet?\nInternet adalah jaringan global.",
        'page_count' => 1,
        'ocr_used' => false,
        'ocr_confidence' => null,
        'extraction_status' => 'success',
        'warnings' => [],
    ]);
});

test('raw submission extraction reads txt file through python', function () {
    Storage::fake('public');
    Storage::disk('public')->put('submissions/jawaban.txt', "Soal 1\nJawaban mentah dari TXT.");

    $service = new RawSubmissionExtractionService();
    $submission = new Submission([
        'submission_id' => 'SUB-TXT-001',
        'file_path' => 'submissions/jawaban.txt',
    ]);

    $result = $service->extract($submission);

    expect($result['detected_content_type'])->toBe('txt');
    expect($result['extraction_method'])->toBe('txt_reader');
    expect($result['raw_text'])->toBe("Soal 1\nJawaban mentah dari TXT.");
    expect($result['page_count'])->toBe(1);
    expect($result['ocr_used'])->toBeFalse();
    expect($result['extraction_status'])->toBe('success');
});

test('raw submission extraction parses docx document text', function () {
    Storage::fake('public');
    $absolutePath = Storage::disk('public')->path('submissions/laporan.docx');
    makeMinimalDocx($absolutePath, ['Soal 1: Jelaskan API.', 'Jawaban: API adalah antarmuka.']);

    $service = new RawSubmissionExtractionService();
    $submission = new Submission([
        'submission_id' => 'SUB-DOCX-001',
        'file_path' => 'submissions/laporan.docx',
    ]);

    $result = $service->extract($submission);

    expect($result['detected_content_type'])->toBe('docx');
    expect($result['extraction_method'])->toBe('docx_parser');
    expect($result['raw_text'])->toContain('Soal 1: Jelaskan API.');
    expect($result['raw_text'])->toContain('Jawaban: API adalah antarmuka.');
    expect($result['extraction_status'])->toBe('success');
});

test('raw submission extraction marks image ocr failed when ocr tool is unavailable', function () {
    Storage::fake('public');
    Storage::disk('public')->put('submissions/foto.png', 'not-important-for-unavailable-tool');
    config()->set('services.ai.extraction.tesseract_binary', 'missing-tesseract-binary-for-test');

    $service = new RawSubmissionExtractionService();
    $submission = new Submission([
        'submission_id' => 'SUB-IMG-001',
        'file_path' => 'submissions/foto.png',
    ]);

    $result = $service->extract($submission);

    expect($result['detected_content_type'])->toBe('image');
    expect($result['extraction_method'])->toBe('OCR');
    expect($result['ocr_used'])->toBeTrue();
    expect($result['ocr_confidence'])->toBe(0.0);
    expect($result['extraction_status'])->toBe('failed');
    expect($result['warnings'])->toContain('ocr_tool_unavailable');
});

function makeMinimalDocx(string $absolutePath, array $paragraphs): void
{
    $directory = dirname($absolutePath);
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $zip = new ZipArchive();
    $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');

    $body = collect($paragraphs)
        ->map(fn ($paragraph) => '<w:p><w:r><w:t>'.htmlspecialchars($paragraph, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>')
        ->implode('');

    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
    $zip->close();
}
