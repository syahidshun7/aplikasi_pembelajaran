<?php

use App\Models\Submission;
use App\Services\Ai\SubmissionArtifactExtractorService;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser as PdfParser;

uses(Tests\TestCase::class);

test('submission artifact extractor merges content and pdf extracted text', function () {
    Storage::fake('public');
    Storage::disk('public')->put('reports/sample.pdf', 'dummy-pdf-binary');

    $parser = Mockery::mock(PdfParser::class);
    $document = Mockery::mock(Document::class);
    $document->shouldReceive('getText')->once()->andReturn("Soal 1: Apa itu API?\nJawaban: API adalah antarmuka.");
    $parser->shouldReceive('parseFile')->once()->andReturn($document);

    config()->set('services.ai.pdf_extraction_enabled', true);
    config()->set('services.ai.artifact_max_chars', 12000);

    $service = new SubmissionArtifactExtractorService($parser);

    $submission = new Submission([
        'content' => 'Ringkasan singkat dari mahasiswa.',
        'file_path' => 'reports/sample.pdf',
    ]);

    $result = $service->extract($submission);

    expect($result['combined_text'])->toContain('[TEXT_SUBMISSION]');
    expect($result['combined_text'])->toContain('Ringkasan singkat dari mahasiswa.');
    expect($result['combined_text'])->toContain('[PDF_EXTRACTED_TEXT]');
    expect($result['combined_text'])->toContain("Soal 1: Apa itu API?\nJawaban: API adalah antarmuka.");
    expect($result['source_flags'])->toContain('text');
    expect($result['source_flags'])->toContain('pdf');
});

test('submission artifact extractor returns warning when no readable source found', function () {
    Storage::fake('public');

    $parser = Mockery::mock(PdfParser::class);
    $service = new SubmissionArtifactExtractorService($parser);

    $submission = new Submission([
        'content' => '',
        'file_path' => '',
    ]);

    $result = $service->extract($submission);

    expect($result['combined_text'])->toBe('[NO_READABLE_ARTIFACT_FOUND]');
    expect($result['warnings'])->toContain('NO_TEXT_SOURCE_DETECTED');
});
