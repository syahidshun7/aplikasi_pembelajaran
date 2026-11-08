<?php

use App\Models\User;

it('includes a complete Ziggy bootstrap on consecutive page responses', function () {
    $first = $this->get(route('login'));
    $second = $this->get(route('login'));

    $first->assertOk()->assertSee('const Ziggy=', false);
    $second->assertOk()->assertSee('const Ziggy=', false);
});

it('renders the redesign dashboard with its production asset path', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('redesign.admin-dashboard'))
        ->assertOk();
});
