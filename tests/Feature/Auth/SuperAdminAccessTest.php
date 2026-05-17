<?php

use App\Models\User;

test('super admin can access admin feature routes', function () {
    $superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $routes = [
        'dashboard',
        'quests.index',
        'materi.index',
        'admin.task-banks.index',
        'admin.events.index',
        'admin.rubrics.index',
        'admin.users.index',
        'admin.jobs.index',
        'admin.shop-items.index',
        'admin.submissions.manage.index',
        'groups.manage',
        'admin.error-logs.index',
    ];

    foreach ($routes as $routeName) {
        $this->actingAs($superAdmin)
            ->get(route($routeName))
            ->assertOk();
    }
});

