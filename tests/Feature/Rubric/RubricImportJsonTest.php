<?php

use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricDescription;
use App\Models\RubricLevel;
use App\Models\User;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

it('imports rubric from json file and maps export style matrix ids', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $payload = json_encode([
        'rubric' => [
            'title' => 'Imported Rubric Alpha',
            'description' => 'Imported from JSON file.',
        ],
        'criteria' => [
            ['id' => 101, 'name' => 'Kebenaran Konsep', 'weight' => 70, 'order' => 1],
            ['id' => 102, 'name' => 'Struktur', 'weight' => 30, 'order' => 2],
        ],
        'levels' => [
            ['id' => 201, 'level' => 1, 'label' => 'Poor', 'score_value' => 1],
            ['id' => 202, 'level' => 2, 'label' => 'Good', 'score_value' => 2],
        ],
        'matrix' => [
            ['criteria_id' => 101, 'level_id' => 201, 'description' => 'Konsep belum tepat.'],
            ['criteria_id' => 102, 'level_id' => 202, 'description' => 'Struktur rapi.'],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $file = UploadedFile::fake()->createWithContent('rubric.json', $payload);

    actingAs($admin);

    $response = post(route('admin.rubrics.import-json'), [
        'import_file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('message', 'RUBRIC_IMPORT_SUCCESS');

    $rubric = Rubric::query()->where('title', 'Imported Rubric Alpha')->first();
    expect($rubric)->not->toBeNull();
    expect((int) $rubric->mentor_id)->toBe((int) $admin->id);
    expect((float) $rubric->max_score)->toBe(100.0);

    expect(RubricCriterion::query()->where('rubric_id', $rubric->id)->count())->toBe(2);
    expect(RubricLevel::query()->where('rubric_id', $rubric->id)->count())->toBe(2);

    $criteriaIds = RubricCriterion::query()->where('rubric_id', $rubric->id)->pluck('id');
    expect(RubricDescription::query()->whereIn('criteria_id', $criteriaIds)->count())->toBe(2);

    $result = session('rubric_import_result');
    expect($result['title'] ?? null)->toBe('Imported Rubric Alpha');
    expect((int) ($result['criteria_count'] ?? 0))->toBe(2);
    expect((int) ($result['levels_count'] ?? 0))->toBe(2);
});

it('rejects rubric import from json text when levels are duplicated', function () {
    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
    ]);

    $payload = json_encode([
        'rubric' => [
            'title' => 'Broken Rubric',
        ],
        'criteria' => [
            ['name' => 'Akurasi', 'weight' => 100, 'order' => 1],
        ],
        'levels' => [
            ['level' => 1, 'label' => 'Poor', 'score_value' => 1],
            ['level' => 1, 'label' => 'Good', 'score_value' => 2],
        ],
    ], JSON_UNESCAPED_UNICODE);

    actingAs($mentor);

    $response = post(route('admin.rubrics.import-json'), [
        'import_json_text' => $payload,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('import_json_text');

    expect(Rubric::query()->where('title', 'Broken Rubric')->exists())->toBeFalse();
});
