<?php

use App\Models\User;

it('sends staff roles from the home page to their redesign dashboard', function (string $role, string $destination) {
    $user = User::factory()->create([
        'role' => $role,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('lobby'))
        ->assertRedirect(route($destination));
})->with([
    'super admin' => [User::ROLE_SUPER_ADMIN, 'redesign.admin-dashboard'],
    'admin' => [User::ROLE_ADMIN, 'redesign.admin-dashboard'],
    'mentor' => [User::ROLE_MENTOR, 'redesign.class-dashboard'],
]);

it('shows the redesign notification history to a mentor', function () {
    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($mentor)
        ->get(route('redesign.notifications'))
        ->assertOk();
});
