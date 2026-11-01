<?php

use App\Models\DoopLabTodo;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guest is redirected to login when opening dooplab dashboard', function () {
    $this->get(route('dooplab.dashboard'))
        ->assertUnauthorized();
});

test('regular user can open dooplab dashboard', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $this->actingAs($user)
        ->get(route('dooplab.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('DoopLab/Dashboard')
            ->has('learning_paths')
        );
});

test('paid member can open dooplab dashboard', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $this->actingAs($user)
        ->get(route('dooplab.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('DoopLab/Dashboard')
            ->has('overview')
            ->has('my_creation_stats')
            ->has('recent_experiments')
            ->has('collaboration')
            ->has('mentors')
            ->has('todos')
            ->has('todo_permissions')
            ->has('todo_assignable_users')
            ->has('learning_paths')
        );
});

test('todo list prioritizes the nearest deadline before items without deadline and completed items', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    DoopLabTodo::query()->create([
        'title' => 'Tanpa deadline',
        'owner_user_id' => $admin->id,
        'assignment_mode' => DoopLabTodo::MODE_SELF,
    ]);

    DoopLabTodo::query()->create([
        'title' => 'Deadline jauh',
        'owner_user_id' => $admin->id,
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'deadline' => now()->addDays(3),
    ]);

    DoopLabTodo::query()->create([
        'title' => 'Deadline dekat',
        'owner_user_id' => $admin->id,
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'deadline' => now()->addHours(3),
    ]);

    DoopLabTodo::query()->create([
        'title' => 'Sudah selesai',
        'owner_user_id' => $admin->id,
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'workflow_status' => DoopLabTodo::STATUS_DONE,
        'deadline' => now()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('dooplab.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('todos.0.title', 'Deadline dekat')
            ->where('todos.1.title', 'Deadline jauh')
            ->where('todos.2.title', 'Tanpa deadline')
            ->where('todos.3.title', 'Sudah selesai')
        );
});
