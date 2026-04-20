<?php

use App\Models\Quest;
use App\Models\ShopItem;
use App\Models\ShopTransaction;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserInventory;
use App\Services\UserRewardSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('purchase time key reduces user gold and records negative gold_change', function () {
    $user = User::factory()->create([
        'gold' => 1000,
        'email_verified_at' => now(),
    ]);

    $item = ShopItem::query()->create([
        'code' => 'TIME_KEY_TEST_A',
        'name' => 'Time Key Test A',
        'price_gold' => 250,
        'is_active' => true,
        'is_stackable' => true,
    ]);

    $this->actingAs($user)
        ->post(route('shop.purchase', $item->id), [
            'quantity' => 2,
        ])
        ->assertRedirect();

    $user->refresh();

    expect((int) $user->gold)->toBe(500);

    $inventoryQty = (int) UserInventory::query()
        ->where('user_id', $user->id)
        ->where('shop_item_id', $item->id)
        ->value('quantity');
    expect($inventoryQty)->toBe(2);

    $tx = ShopTransaction::query()
        ->where('user_id', $user->id)
        ->where('shop_item_id', $item->id)
        ->where('type', 'purchase')
        ->latest('id')
        ->firstOrFail();

    expect((int) $tx->quantity)->toBe(2);
    expect((int) $tx->gold_change)->toBe(-500);
});

it('shop transaction model normalizes gold_change by transaction type', function () {
    $user = User::factory()->create();

    $item = ShopItem::query()->create([
        'code' => 'TIME_KEY_TEST_B',
        'name' => 'Time Key Test B',
        'price_gold' => 250,
        'is_active' => true,
        'is_stackable' => true,
    ]);

    $purchaseTx = ShopTransaction::query()->create([
        'user_id' => $user->id,
        'shop_item_id' => $item->id,
        'type' => 'purchase',
        'quantity' => 1,
        'gold_change' => 250,
        'note' => 'Malformed positive purchase value',
    ])->fresh();

    $consumeTx = ShopTransaction::query()->create([
        'user_id' => $user->id,
        'shop_item_id' => $item->id,
        'type' => 'consume_unlock',
        'quantity' => 1,
        'gold_change' => 999,
        'note' => 'Malformed non-zero consume value',
    ])->fresh();

    expect((int) $purchaseTx->gold_change)->toBe(-250);
    expect((int) $consumeTx->gold_change)->toBe(0);
});

it('reward sync treats malformed purchase as expense and consume unlock as zero', function () {
    $user = User::factory()->create([
        'gold' => 0,
    ]);

    $item = ShopItem::query()->create([
        'code' => 'TIME_KEY_TEST_C',
        'name' => 'Time Key Test C',
        'price_gold' => 250,
        'is_active' => true,
        'is_stackable' => true,
    ]);

    $quest = Quest::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Quest Gold Sync Test',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => 'Available',
    ]);

    Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'content' => 'Submission reward source',
        'status' => 'Approved',
        'grade' => 100,
        'earned_exp' => 1000,
        'earned_gold' => 1000,
    ]);

    // Insert directly to keep malformed historical data shape (bypassing model normalization).
    DB::table('shop_transactions')->insert([
        'user_id' => $user->id,
        'shop_item_id' => $item->id,
        'type' => 'purchase',
        'quantity' => 1,
        'gold_change' => 250,
        'note' => 'Historical malformed purchase row',
        'meta' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('shop_transactions')->insert([
        'user_id' => $user->id,
        'shop_item_id' => $item->id,
        'type' => 'consume_unlock',
        'quantity' => 1,
        'gold_change' => 500,
        'note' => 'Historical malformed consume row',
        'meta' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(UserRewardSyncService::class)->sync((int) $user->id);
    $user->refresh();

    expect((int) $user->gold)->toBe(750);
});
