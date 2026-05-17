<?php

use App\Models\DoopLabTodo;
use App\Models\User;

test('student can create and toggle own dooplab todo', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $this->actingAs($student)
        ->post(route('dooplab.todos.store'), [
            'title' => 'Belajar arsitektur API',
            'description' => 'Buat ringkasan endpoint',
            'assignment_mode' => DoopLabTodo::MODE_SELF,
        ])
        ->assertRedirect();

    $todo = DoopLabTodo::query()->where('owner_user_id', $student->id)->firstOrFail();

    expect($todo->assignment_mode)->toBe(DoopLabTodo::MODE_SELF);
    expect((int) $todo->mentor_user_id)->toBe(0);
    expect((bool) $todo->is_completed)->toBeFalse();

    $this->actingAs($student)
        ->patch(route('dooplab.todos.toggle', $todo->uuid))
        ->assertRedirect();

    $todo->refresh();
    expect((bool) $todo->is_completed)->toBeTrue();
    expect((int) $todo->completed_by_user_id)->toBe((int) $student->id);
});

test('student cannot force mentor assignment mode when creating dooplab todo', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $otherStudent = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $this->actingAs($student)
        ->post(route('dooplab.todos.store'), [
            'title' => 'Paksa mode mentor',
            'assignment_mode' => DoopLabTodo::MODE_MENTOR,
            'owner_user_id' => $otherStudent->id,
        ])
        ->assertRedirect();

    $todo = DoopLabTodo::query()->latest('id')->firstOrFail();

    expect($todo->assignment_mode)->toBe(DoopLabTodo::MODE_SELF);
    expect((int) $todo->owner_user_id)->toBe((int) $student->id);
    expect((int) $todo->mentor_user_id)->toBe(0);
});

test('mentor can create mentor-assigned todo and toggle it', function () {
    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $this->actingAs($mentor)
        ->post(route('dooplab.todos.store'), [
            'title' => 'Review mini project',
            'assignment_mode' => DoopLabTodo::MODE_MENTOR,
            'owner_user_id' => $student->id,
        ])
        ->assertRedirect();

    $todo = DoopLabTodo::query()->latest('id')->firstOrFail();

    expect($todo->assignment_mode)->toBe(DoopLabTodo::MODE_MENTOR);
    expect((int) $todo->owner_user_id)->toBe((int) $student->id);
    expect((int) $todo->mentor_user_id)->toBe((int) $mentor->id);

    $this->actingAs($mentor)
        ->patch(route('dooplab.todos.toggle', $todo->uuid))
        ->assertRedirect();

    $todo->refresh();
    expect((bool) $todo->is_completed)->toBeTrue();
    expect((int) $todo->completed_by_user_id)->toBe((int) $mentor->id);
});

test('student cannot toggle mentor-assigned todo', function () {
    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $todo = DoopLabTodo::query()->create([
        'title' => 'Todo dari mentor',
        'assignment_mode' => DoopLabTodo::MODE_MENTOR,
        'owner_user_id' => $student->id,
        'mentor_user_id' => $mentor->id,
        'is_completed' => false,
    ]);

    $this->actingAs($student)
        ->patch(route('dooplab.todos.toggle', $todo->uuid))
        ->assertForbidden();

    $todo->refresh();
    expect((bool) $todo->is_completed)->toBeFalse();
});

test('dooplab todo supports schedule fields on create and edit', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $startAt = now()->addHours(2);
    $deadline = now()->addDay();

    $this->actingAs($student)
        ->post(route('dooplab.todos.store'), [
            'title' => 'Todo berjadwal',
            'description' => 'Dengan start dan deadline',
            'assignment_mode' => DoopLabTodo::MODE_SELF,
            'start_at' => $startAt->toDateTimeString(),
            'deadline' => $deadline->toDateTimeString(),
            'notify_deadline_email' => true,
        ])
        ->assertRedirect();

    $todo = DoopLabTodo::query()->latest('id')->firstOrFail();

    expect($todo->start_at)->not->toBeNull();
    expect($todo->deadline)->not->toBeNull();
    expect($todo->deadline?->greaterThan($todo->start_at))->toBeTrue();
    expect((bool) $todo->notify_deadline_email)->toBeTrue();
    expect($todo->deadline_reminded_at)->toBeNull();
    $initialStartAt = $todo->start_at?->toDateTimeString();
    $initialDeadline = $todo->deadline?->toDateTimeString();

    $newStartAt = now()->addHours(5);
    $newDeadline = now()->addDays(2);

    $this->actingAs($student)
        ->patch(route('dooplab.todos.update', $todo->uuid), [
            'title' => 'Todo berjadwal update',
            'description' => 'Update jadwal',
            'start_at' => $newStartAt->toDateTimeString(),
            'deadline' => $newDeadline->toDateTimeString(),
            'notify_deadline_email' => false,
        ])
        ->assertRedirect();

    $todo->refresh();

    expect($todo->title)->toBe('Todo berjadwal update');
    expect($todo->start_at)->not->toBeNull();
    expect($todo->deadline)->not->toBeNull();
    expect($todo->start_at?->toDateTimeString())->not->toBe($initialStartAt);
    expect($todo->deadline?->toDateTimeString())->not->toBe($initialDeadline);
    expect((bool) $todo->notify_deadline_email)->toBeFalse();
});
