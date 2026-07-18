<?php

use App\Models\ShopItem;
use App\Models\User;
use App\Models\UserInventory;
use App\Models\UserInventoryLog;
use Inertia\Testing\AssertableInertia;

it('paginates inventory items and logs independently while keeping global totals', function () {
    $user = User::factory()->create();

    collect(range(1, 13))->each(function (int $index) use ($user) {
        $item = ShopItem::query()->create([
            'code' => "INVENTORY_PAGINATION_{$index}",
            'name' => "Inventory Pagination {$index}",
            'price_gold' => 100,
            'is_active' => true,
            'is_stackable' => true,
        ]);

        UserInventory::query()->create([
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
            'quantity' => $index,
        ]);

        UserInventoryLog::query()->create([
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
            'quantity_before' => 0,
            'quantity_after' => $index,
            'quantity_change' => $index,
            'type' => UserInventoryLog::TYPE_PURCHASE,
            'note' => "Inventory pagination log {$index}",
        ]);
    });

    $this->actingAs($user)
        ->get(route('inventory.index', [
            'items_page' => 2,
            'logs_page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/Index')
            ->has('inventories.data', 1)
            ->where('inventories.current_page', 2)
            ->where('inventories.last_page', 2)
            ->where('inventories.total', 13)
            ->where('inventories.prev_page_url', fn ($url) => str_contains($url, 'items_page=1') && str_contains($url, 'logs_page=2'))
            ->has('logs.data', 3)
            ->where('logs.current_page', 2)
            ->where('logs.last_page', 2)
            ->where('logs.total', 13)
            ->where('logs.prev_page_url', fn ($url) => str_contains($url, 'logs_page=1') && str_contains($url, 'items_page=2'))
            ->where('summary.unique_items', 13)
            ->where('summary.total_quantity', 91)
        );
});
