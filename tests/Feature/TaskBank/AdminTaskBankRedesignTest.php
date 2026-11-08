<?php

use App\Models\TaskBank;
use App\Models\TaskQuestion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

test('redesign task bank table filters active draft and archived banks using real records', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    TaskBank::query()->create(['name' => 'Active Bank', 'assessment_type' => 'essay', 'is_active' => true]);
    TaskBank::query()->create(['name' => 'Draft Bank', 'assessment_type' => 'essay', 'is_active' => false]);
    $archived = TaskBank::query()->create(['name' => 'Archived Bank', 'assessment_type' => 'essay', 'is_active' => true]);
    $archived->delete();

    $this->actingAs($admin)->get(route('redesign.task-banks', ['status' => 'active']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/TaskBanks', false)
            ->where('taskBanks.total', 1)
            ->where('taskBanks.data.0.name', 'Active Bank')
            ->etc());

    $this->get(route('redesign.task-banks', ['status' => 'draft']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/TaskBanks', false)
            ->where('taskBanks.total', 1)
            ->where('taskBanks.data.0.name', 'Draft Bank')
            ->etc());

    $this->get(route('redesign.task-banks', ['status' => 'archive']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/TaskBanks', false)
            ->where('taskBanks.total', 1)
            ->where('taskBanks.data.0.name', 'Archived Bank')
            ->etc());

    $this->patch(route('admin.task-banks.restore', $archived->uuid))->assertRedirect();
    expect($archived->fresh()->trashed())->toBeFalse();
});

test('redesign task bank settings create and update a bank with game duration semantics', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->get(route('redesign.task-banks.create'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/TaskBankForm', false)->etc());

    $payload = [
        'name' => 'Platforming Basics',
        'description' => 'Latihan platforming.',
        'job_role_id' => null,
        'assessment_type' => 'platforming',
        'duration' => 120,
        'has_time_limit' => false,
        'question_display_mode' => 'single',
        'is_active' => true,
        'redesign_form' => 1,
    ];

    $this->post(route('admin.task-banks.store'), $payload)
        ->assertRedirect(route('redesign.task-banks'));

    $bank = TaskBank::query()->where('name', 'Platforming Basics')->firstOrFail();
    expect($bank->duration)->toBe(120)
        ->and($bank->has_time_limit)->toBeTrue()
        ->and($bank->question_display_mode)->toBe('all');

    $this->get(route('redesign.task-banks.edit', $bank->uuid))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/TaskBankForm', false)
            ->where('taskBank.name', 'Platforming Basics')->etc());

    $this->put(route('admin.task-banks.update', $bank->uuid), [
        ...$payload,
        'name' => 'Platforming Advanced',
        'duration' => 180,
        'is_active' => false,
    ])->assertRedirect(route('redesign.task-banks'));

    expect($bank->fresh()->name)->toBe('Platforming Advanced')
        ->and($bank->fresh()->duration)->toBe(180)
        ->and($bank->fresh()->is_active)->toBeFalse();
});

test('redesign question editor and JSON import open for a real bank', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $bank = TaskBank::query()->create(['name' => 'Question Editor Bank', 'assessment_type' => 'multiple_choice', 'is_active' => true]);

    $this->actingAs($admin)->get(route('redesign.task-banks.questions', $bank->uuid))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/QuestionEditor', false)
            ->where('taskBank.name', 'Question Editor Bank')->etc());

    $this->get(route('redesign.task-banks.import', $bank->uuid))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/QuestionImport', false)
            ->where('taskBank.name', 'Question Editor Bank')->etc());
});

test('question options keep their image and correct answer when reordered', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $bank = TaskBank::query()->create(['name' => 'Image Options Bank', 'assessment_type' => 'multiple_choice', 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.task-banks.tasks.store', $bank->uuid), [
        'question_text' => 'Pilih gambar yang benar',
        'question_type' => 'multiple_choice',
        'options' => [
            ['key' => 'first', 'text' => 'Pertama'],
            ['key' => 'second', 'text' => 'Kedua'],
        ],
        'option_images' => [UploadedFile::fake()->image('pertama.png'), null],
        'answer_key' => 'Pertama', 'weight' => 1, 'sort_order' => 1, 'is_active' => true,
    ])->assertRedirect();

    $question = TaskQuestion::query()->where('task_bank_id', $bank->id)->firstOrFail();
    $path = $question->options_json[0]['image_path'];
    Storage::disk('public')->assertExists($path);

    $this->post(route('admin.task-banks.tasks.update', ['taskBank' => $bank->uuid, 'question' => $question->uuid]), [
        '_method' => 'put', 'question_text' => 'Pilih gambar yang benar',
        'question_type' => 'multiple_choice',
        'options' => [
            ['key' => 'second', 'text' => 'Kedua'],
            ['key' => 'first', 'text' => 'Pertama', 'existing_image_path' => $path],
        ],
        'answer_key' => 'Pertama', 'weight' => 1, 'sort_order' => 1, 'is_active' => true,
    ])->assertRedirect();

    $question->refresh();
    expect($question->answer_key)->toBe('Pertama')
        ->and($question->options_json[1]['image_path'])->toBe($path);
    Storage::disk('public')->assertExists($path);
});
