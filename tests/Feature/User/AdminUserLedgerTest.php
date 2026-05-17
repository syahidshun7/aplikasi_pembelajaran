<?php

use App\Models\DailyQuest;
use App\Models\DailyQuestDefinition;
use App\Models\Quest;
use App\Models\ShopItem;
use App\Models\ShopTransaction;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserGoldAdjustment;
use Inertia\Testing\AssertableInertia;

it('admin can view unified user gold ledger with income and expense summary', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $target = User::factory()->create([
        'role' => User::ROLE_USER,
        'gold' => 900,
    ]);

    $quest = Quest::query()->create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'title' => 'Ledger Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $target->id,
        'content' => 'Rewarded submission',
        'status' => 'Approved',
        'grade' => 80,
        'earned_exp' => 300,
        'earned_gold' => 300,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $definition = DailyQuestDefinition::query()->firstOrFail();
    DailyQuest::query()->create([
        'daily_quest_definition_id' => $definition->id,
        'user_id' => $target->id,
        'quest_date' => now()->toDateString(),
        'title' => 'Daily Reward',
        'description' => 'Daily reward test',
        'activity_type' => DailyQuestDefinition::ACTIVITY_LOGIN,
        'target_value' => 1,
        'progress_value' => 1,
        'reward_exp' => 10,
        'reward_gold' => 20,
        'sort_order' => 1,
        'status' => DailyQuest::STATUS_CLAIMED,
        'completed_at' => now()->subDay(),
        'claimed_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    $timeKey = ShopItem::query()->create([
        'code' => 'TIME_KEY_LEDGER',
        'name' => 'Time Key Ledger',
        'price_gold' => 250,
        'is_active' => true,
        'is_stackable' => true,
    ]);

    ShopTransaction::query()->create([
        'user_id' => $target->id,
        'shop_item_id' => $timeKey->id,
        'type' => 'purchase',
        'quantity' => 1,
        'gold_change' => -250,
        'note' => 'Purchase from user shop',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    ShopTransaction::query()->create([
        'user_id' => $target->id,
        'shop_item_id' => $timeKey->id,
        'type' => 'consume_unlock',
        'quantity' => 1,
        'gold_change' => 0,
        'note' => 'Use Time Key to reopen late quest',
        'created_at' => now()->subHours(20),
        'updated_at' => now()->subHours(20),
    ]);

    ShopTransaction::query()->create([
        'user_id' => $target->id,
        'shop_item_id' => $timeKey->id,
        'type' => 'purchase',
        'quantity' => 1,
        'gold_change' => -100,
        'note' => 'Purchase later cancelled by admin',
        'meta' => [
            'admin_cancelled_at' => now()->subHours(10)->toDateTimeString(),
            'refund_gold' => 100,
        ],
        'created_at' => now()->subHours(12),
        'updated_at' => now()->subHours(12),
    ]);

    UserGoldAdjustment::query()->create([
        'user_id' => $target->id,
        'admin_user_id' => $admin->id,
        'gold_before' => 500,
        'gold_after' => 450,
        'gold_change' => -50,
        'reason' => 'Admin user profile update',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.ledger', $target->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Users/Admin/Ledger')
            ->where('user.id', $target->id)
            ->where('summary.income_total', 420)
            ->where('summary.expense_total', 400)
            ->where('summary.net_total', 20)
            ->where('summary.transaction_count', 7)
            ->has('ledger.data', 7)
            ->where('sourceBreakdown.0.key', 'submission_reward')
        );
});

it('admin update logs gold adjustment audit entry', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $target = User::factory()->create([
        'role' => User::ROLE_USER,
        'gold' => 200,
        'exp' => 100,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $target->id), [
            'name' => $target->name,
            'username' => $target->username,
            'email' => $target->email,
            'role' => User::ROLE_USER,
            'job_id' => null,
            'gold' => 350,
            'exp' => 100,
            'level' => 1,
            'bio' => '',
            'experience' => '',
            'location' => '',
            'skills_text' => '',
            'remove_avatar' => false,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect()
        ->assertSessionHas('message', 'USER_DATA_UPDATED');

    $adjustment = UserGoldAdjustment::query()
        ->where('user_id', $target->id)
        ->latest('id')
        ->first();

    expect($adjustment)->not->toBeNull();
    expect((int) $adjustment->gold_before)->toBe(200);
    expect((int) $adjustment->gold_after)->toBe(350);
    expect((int) $adjustment->gold_change)->toBe(150);
    expect((int) $adjustment->admin_user_id)->toBe((int) $admin->id);
});
