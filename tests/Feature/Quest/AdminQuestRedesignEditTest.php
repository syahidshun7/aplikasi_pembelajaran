<?php

use App\Models\Quest;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('admin can open redesign quest editor', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $quest = Quest::query()->create([
        'title' => 'Quest edit',
        'description' => 'Uji form',
        'status' => Quest::STATUS_AVAILABLE,
        'difficulty' => 'C-Rank',
    ]);

    $this->actingAs($admin)->get(route('redesign.quests.edit', $quest->uuid))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/QuestForm', false)
            ->where('quest.uuid', $quest->uuid)->etc());
});

test('archived quest can be listed and restored', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $quest = Quest::query()->create([
        'title' => 'Quest arsip', 'description' => 'Uji',
        'status' => Quest::STATUS_AVAILABLE, 'difficulty' => 'C-Rank',
    ]);
    $this->actingAs($admin)->delete(route('quests.destroy', $quest->uuid))->assertRedirect();
    expect(Quest::find($quest->id))->toBeNull();

    $this->actingAs($admin)->get(route('redesign.quests', ['view' => 'trash']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/Quests', false)
            ->where('quests.data.0.uuid', $quest->uuid)->etc());
    $this->patch(route('quests.restore', $quest->uuid))->assertRedirect();
    expect(Quest::find($quest->id))->not->toBeNull();
});
