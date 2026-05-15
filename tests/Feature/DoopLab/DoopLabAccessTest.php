<?php

use App\Models\User;

test('guest is redirected to login when opening dooplab', function () {
    $this->get(route('dooplab.index'))
        ->assertRedirect(route('landing'));
});

test('non paid member can open dooplab landing', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $this->actingAs($user)
        ->get(route('dooplab.index'))
        ->assertOk();
});

test('paid member can access dooplab', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $this->actingAs($user)
        ->get(route('dooplab.index'))
        ->assertOk();
});

test('staff can access dooplab', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $this->actingAs($admin)
        ->get(route('dooplab.index'))
        ->assertOk();
});
