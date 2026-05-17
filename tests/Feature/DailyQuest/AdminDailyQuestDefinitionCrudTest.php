<?php

use App\Models\DailyQuest;
use App\Models\DailyQuestDefinition;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('admin can open daily quest definition management page', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.daily-quest-definitions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('DailyQuests/Admin/Index')
            ->has('definitions.data')
            ->has('activityTypes')
            ->has('stats')
        );
});

test('admin can update daily quest rewards via crud endpoint', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $definition = DailyQuestDefinition::query()
        ->where('activity_type', DailyQuestDefinition::ACTIVITY_LOGIN)
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.daily-quest-definitions.update', $definition->id), [
            'code' => (string) $definition->code,
            'title' => (string) $definition->title,
            'description' => (string) ($definition->description ?? ''),
            'activity_type' => DailyQuestDefinition::ACTIVITY_LOGIN,
            'target_value' => 1,
            'reward_exp' => 333,
            'reward_gold' => 222,
            'sort_order' => (int) ($definition->sort_order ?? 1),
            'is_active' => true,
            'activity_steps_text' => "Step one\nStep two",
            'meta_category' => 'engagement',
            'meta_icon' => 'fi fi-rr-enter',
        ])
        ->assertRedirect();

    $definition->refresh();

    expect((int) $definition->reward_exp)->toBe(333);
    expect((int) $definition->reward_gold)->toBe(222);
    expect(data_get($definition->meta, 'activity_steps.0'))->toBe('Step one');
    expect(data_get($definition->meta, 'activity_steps.1'))->toBe('Step two');
});

test('non admin cannot access daily quest definition management page', function () {
    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($mentor)
        ->get(route('admin.daily-quest-definitions.index'))
        ->assertRedirect('/');
});

test('updated definition rewards are used for newly generated daily quests', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $definition = DailyQuestDefinition::query()
        ->where('activity_type', DailyQuestDefinition::ACTIVITY_LOGIN)
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.daily-quest-definitions.update', $definition->id), [
            'code' => (string) $definition->code,
            'title' => (string) $definition->title,
            'description' => (string) ($definition->description ?? ''),
            'activity_type' => DailyQuestDefinition::ACTIVITY_LOGIN,
            'target_value' => 1,
            'reward_exp' => 777,
            'reward_gold' => 555,
            'sort_order' => (int) ($definition->sort_order ?? 1),
            'is_active' => true,
            'activity_steps_text' => '',
            'meta_category' => 'engagement',
            'meta_icon' => 'fi fi-rr-enter',
        ])
        ->assertRedirect();

    $this->post(route('logout'))->assertRedirect('/');

    $player = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $this->post('/login', [
        'email' => $player->email,
        'password' => 'password',
    ])->assertRedirect(route('lobby', absolute: false));

    $dailyQuest = DailyQuest::query()
        ->where('user_id', (int) $player->id)
        ->where('activity_type', DailyQuestDefinition::ACTIVITY_LOGIN)
        ->latest('id')
        ->firstOrFail();

    expect((int) $dailyQuest->reward_exp)->toBe(777);
    expect((int) $dailyQuest->reward_gold)->toBe(555);
});
