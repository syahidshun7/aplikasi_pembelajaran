<?php

use App\Models\TaskBank;
use App\Models\TaskQuestion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    Storage::fake('local');
});

it('imports valid task questions from json and skips invalid rows when requested', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $taskBank = TaskBank::query()->create([
        'name' => 'Import Test Bank',
        'assessment_type' => 'mixed',
        'is_active' => true,
    ]);

    $payload = json_encode([
        [
            'pertanyaan' => 'Ibukota Indonesia adalah?',
            'tipe_soal' => 'multiple_choice',
            'opsi' => [
                'A' => 'Jakarta',
                'B' => 'Bandung',
                'C' => 'Surabaya',
                'D' => 'Medan',
            ],
            'jawaban' => 'A',
            'bobot' => 1,
            'urutan' => 1,
            'is_active' => true,
        ],
        [
            'pertanyaan' => 'Jelaskan fungsi RAM pada komputer.',
            'tipe_soal' => 'essay',
            'bobot' => 2,
            'urutan' => 2,
            'is_active' => true,
        ],
        [
            'pertanyaan' => '',
            'tipe_soal' => 'multiple_choice',
            'opsi' => [
                'A' => 'Salah',
                'B' => 'Benar',
            ],
            'jawaban' => 'B',
        ],
    ], JSON_UNESCAPED_UNICODE);

    $file = UploadedFile::fake()->createWithContent('questions.json', $payload);

    actingAs($admin);

    $response = post(route('admin.task-banks.tasks.import-json', $taskBank->uuid), [
        'import_file' => $file,
        'skip_invalid' => '1',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('task_bank_import_result');

    expect(TaskQuestion::query()->where('task_bank_id', $taskBank->id)->count())->toBe(2);

    $firstQuestion = TaskQuestion::query()
        ->where('task_bank_id', $taskBank->id)
        ->where('question_text', 'Ibukota Indonesia adalah?')
        ->first();

    expect($firstQuestion)->not->toBeNull();
    expect($firstQuestion->question_type)->toBe('multiple_choice');
    expect($firstQuestion->options_json)->toBe(['Jakarta', 'Bandung', 'Surabaya', 'Medan']);
    expect($firstQuestion->answer_key)->toBe('Jakarta');

    $result = session('task_bank_import_result');
    expect($result['success_count'])->toBe(2);
    expect($result['failed_count'])->toBe(1);
    expect($result['skipped_invalid'])->toBeTrue();
});

it('rejects the import in strict mode when duplicate rows are found', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $taskBank = TaskBank::query()->create([
        'name' => 'Strict Import Bank',
        'assessment_type' => 'multiple_choice',
        'is_active' => true,
    ]);

    $payload = json_encode([
        [
            'pertanyaan' => 'Planet terbesar di tata surya adalah?',
            'opsi' => [
                'A' => 'Mars',
                'B' => 'Jupiter',
                'C' => 'Venus',
                'D' => 'Saturnus',
            ],
            'jawaban' => 'B',
        ],
        [
            'pertanyaan' => 'Planet terbesar di tata surya adalah?',
            'opsi' => [
                'A' => 'Mars',
                'B' => 'Jupiter',
                'C' => 'Venus',
                'D' => 'Saturnus',
            ],
            'jawaban' => 'B',
        ],
    ], JSON_UNESCAPED_UNICODE);

    $file = UploadedFile::fake()->createWithContent('strict.json', $payload);

    actingAs($admin);

    $response = post(route('admin.task-banks.tasks.import-json', $taskBank->uuid), [
        'import_file' => $file,
        'skip_invalid' => '0',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('import_file');

    expect(TaskQuestion::query()->where('task_bank_id', $taskBank->id)->count())->toBe(0);

    $result = session('task_bank_import_result');
    expect($result['success_count'])->toBe(0);
    expect($result['failed_count'])->toBeGreaterThan(0);
    expect($result['skipped_invalid'])->toBeFalse();
});
