<?php

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
