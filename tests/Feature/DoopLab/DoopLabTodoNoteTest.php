<?php

use App\Models\DoopLabTodo;
use App\Models\DoopLabTodoNote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('owner can add note and proof image to dooplab todo', function () {
    Storage::fake('public');

    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $todo = DoopLabTodo::query()->create([
        'title' => 'Kerjakan modul API',
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'owner_user_id' => $student->id,
        'is_completed' => false,
    ]);

    $file = UploadedFile::fake()->image('bukti.png', 640, 480);

    $this->actingAs($student)
        ->post(route('dooplab.todos.notes.store', $todo->uuid), [
            'note' => 'Saya sudah mengerjakan task ini.',
            'image' => $file,
        ])
        ->assertRedirect();

    $note = DoopLabTodoNote::query()->where('todo_id', $todo->id)->latest('id')->firstOrFail();

    expect((int) $note->author_user_id)->toBe((int) $student->id);
    expect((string) $note->note)->toBe('Saya sudah mengerjakan task ini.');
    expect((string) $note->image_path)->not->toBe('');
    Storage::disk('public')->assertExists($note->image_path);
});

test('assigned mentor can add note to mentor todo', function () {
    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $todo = DoopLabTodo::query()->create([
        'title' => 'Review tugas akhir',
        'assignment_mode' => DoopLabTodo::MODE_MENTOR,
        'owner_user_id' => $student->id,
        'mentor_user_id' => $mentor->id,
        'is_completed' => false,
    ]);

    $this->actingAs($mentor)
        ->post(route('dooplab.todos.notes.store', $todo->uuid), [
            'note' => 'Progress bagus, lanjutkan.',
        ])
        ->assertRedirect();

    $note = DoopLabTodoNote::query()->where('todo_id', $todo->id)->latest('id')->firstOrFail();

    expect((int) $note->author_user_id)->toBe((int) $mentor->id);
    expect((string) $note->note)->toBe('Progress bagus, lanjutkan.');
});

test('non participant cannot add note to dooplab todo', function () {
    $owner = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $otherStudent = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $todo = DoopLabTodo::query()->create([
        'title' => 'Task milik orang lain',
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'owner_user_id' => $owner->id,
        'is_completed' => false,
    ]);

    $this->actingAs($otherStudent)
        ->post(route('dooplab.todos.notes.store', $todo->uuid), [
            'note' => 'Saya coba kirim komentar.',
        ])
        ->assertForbidden();

    expect(DoopLabTodoNote::query()->where('todo_id', $todo->id)->count())->toBe(0);
});

test('mentor cannot add note to unrelated self todo', function () {
    $owner = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
    ]);

    $todo = DoopLabTodo::query()->create([
        'title' => 'Todo self member',
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'owner_user_id' => $owner->id,
        'is_completed' => false,
    ]);

    $this->actingAs($mentor)
        ->post(route('dooplab.todos.notes.store', $todo->uuid), [
            'note' => 'Mentor mencoba komentar di todo yang bukan binaannya.',
        ])
        ->assertForbidden();

    expect(DoopLabTodoNote::query()->where('todo_id', $todo->id)->count())->toBe(0);
});
