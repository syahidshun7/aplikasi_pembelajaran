<?php

use App\Models\DailyQuestDefinition;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('redesign daily quest list and definition form use persisted records', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $active = DailyQuestDefinition::query()->create([
        'code' => 'daily_redesign_login', 'title' => 'Login harian', 'activity_type' => 'login',
        'target_value' => 1, 'reward_exp' => 10, 'reward_gold' => 5,
        'sort_order' => 1, 'is_active' => true, 'meta' => ['category' => 'engagement'],
    ]);
    DailyQuestDefinition::query()->create([
        'code' => 'daily_redesign_submit', 'title' => 'Kirim quest', 'activity_type' => 'quest_submission',
        'target_value' => 1, 'reward_exp' => 20, 'reward_gold' => 0,
        'sort_order' => 2, 'is_active' => false,
    ]);

    $this->actingAs($admin)->get(route('redesign.daily-quests', ['status' => 'active', 'search' => 'daily_redesign_login']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/DailyQuests', false)
            ->where('definitions.total', 1)
            ->where('definitions.data.0.code', 'daily_redesign_login')->etc());

    $this->get(route('redesign.daily-quests.create'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/DailyQuestForm', false)->etc());
    $this->get(route('redesign.daily-quests.edit', $active->id))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/DailyQuestForm', false)
            ->where('definition.code', 'daily_redesign_login')->etc());

    $this->put(route('admin.daily-quest-definitions.update', $active->id), [
        'code' => 'daily_redesign_login', 'title' => 'Login tiap hari',
        'description' => 'Buka aplikasi.', 'activity_type' => 'login',
        'target_value' => 2, 'reward_exp' => 12, 'reward_gold' => 6,
        'sort_order' => 1, 'is_active' => true,
        'activity_steps_text' => 'Buka aplikasi.', 'meta_category' => 'engagement',
        'meta_icon' => 'fi fi-rr-enter', 'redesign_form' => 1,
    ])->assertRedirect(route('redesign.daily-quests'));

    expect($active->fresh()->title)->toBe('Login tiap hari')
        ->and($active->fresh()->target_value)->toBe(2);
});
