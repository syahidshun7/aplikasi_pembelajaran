<?php

use App\Models\ErrorLog;
use App\Models\ShopItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('operational redesign uses live logs notifications and trash', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $deleted = User::factory()->create(['name' => 'Akun dipulihkan']);
    $deleted->delete();
    ErrorLog::create(['trace_id' => 'trace-redesign', 'exception_class' => 'RuntimeException', 'message' => 'Uji log', 'status_code' => 500, 'file' => 'test.php', 'line' => 1]);

    $this->actingAs($admin)->get(route('redesign.error-logs', ['status' => 500]))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/ErrorLogs', false)
            ->where('logs.data.0.trace_id', 'trace-redesign')->etc());
    $this->get(route('redesign.notifications'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/Notifications', false)->etc());
    $this->get(route('redesign.trash'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/Trash', false)
            ->where('items.0.name', 'Akun dipulihkan')->etc());
});

test('shop item in trash can be restored or permanently deleted', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $item = ShopItem::create(['code' => 'TRASH_TEST', 'name' => 'Item arsip', 'price_gold' => 10, 'is_active' => false, 'is_stackable' => true]);
    $item->delete();

    $this->actingAs($admin)->get(route('redesign.trash'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.0.type', 'shop_item')->etc());
    $this->patch(route('redesign.trash.restore', ['shop_item', $item->id]))->assertRedirect();
    expect(ShopItem::find($item->id))->not->toBeNull();

    $item->delete();
    $this->delete(route('redesign.trash.force-destroy', ['shop_item', $item->id]))->assertRedirect();
    expect(ShopItem::withTrashed()->find($item->id))->toBeNull();
});
