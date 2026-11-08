<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('community redesign pages are separate and require admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($member)->get(route('redesign.users'))->assertRedirect();

    $this->actingAs($admin)->get(route('redesign.users'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/Users', false)
            ->where('users.total', 2)->etc());
    $this->get(route('redesign.users.edit', $member->id))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/UserForm', false)
            ->where('user.id', $member->id)->etc());
    $this->get(route('redesign.users.ledger', $member->id))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/UserLedger', false)->etc());

    foreach ([
        'redesign.doopnews' => 'DoopNews',
        'redesign.doopnews.create' => 'DoopNewsForm',
        'redesign.shop-items' => 'ShopItems',
        'redesign.shop-items.create' => 'ShopItemForm',
        'redesign.profile-skins' => 'ProfileSkins',
        'redesign.profile-skins.create' => 'ProfileSkinForm',
    ] as $routeName => $component) {
        $this->get(route($routeName))
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Redesign/AdminDashboard/'.$component, false)->etc());
    }

    $this->get(route('admin.users.index'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Users/Admin/Index', false)->etc());
});

test('user redesign keeps legacy search role rank and grade filters', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['name' => 'Peserta Filter', 'role' => User::ROLE_USER, 'gold' => 75]);

    $this->actingAs($admin)->get(route('redesign.users', [
        'search' => 'Peserta Filter',
        'role' => User::ROLE_USER,
        'rank_by' => 'highest_gold',
        'grade_order' => 'desc',
        'view' => 'active',
    ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Redesign/AdminDashboard/Users', false)
        ->where('filters.rank_by', 'highest_gold')
        ->where('filters.grade_order', 'desc')
        ->where('users.total', 1)
        ->where('users.data.0.id', $user->id)->etc());
});
