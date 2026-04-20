<?php

namespace App\Http\Controllers;

use App\Models\ShopItem;
use App\Models\ShopTransaction;
use App\Models\User;
use App\Models\UserInventory;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ShopController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $shopCacheVersion = CacheVersion::get('shop');
        $items = Cache::remember(
            "shop.items.v{$shopCacheVersion}",
            now()->addMinutes(5),
            fn () => ShopItem::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->select([
                    'id',
                    'name',
                    'description',
                    'price_gold',
                    'icon_path',
                    'is_active',
                ])
                ->get()
        );

        $inventories = UserInventory::query()
            ->where('user_id', $user->id)
            ->pluck('quantity', 'shop_item_id');

        $items = $items->map(function ($item) use ($inventories) {
            $item->owned_qty = (int) ($inventories[$item->id] ?? 0);
            return $item;
        });

        $transactions = ShopTransaction::query()
            ->with('item:id,name,code')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Shop/Index', [
            'items' => $items,
            'gold' => (int) ($user->gold ?? 0),
            'transactions' => $transactions,
        ]);
    }

    public function purchase(Request $request, ShopItem $item)
    {
        if (! $item->is_active) {
            abort(404);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $qty = (int) $validated['quantity'];

        DB::transaction(function () use ($item, $qty) {
            $lockedItem = ShopItem::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $unitPrice = (int) ($lockedItem->price_gold ?? 0);
            if ($unitPrice < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Harga item tidak valid. Silakan hubungi admin.',
                ]);
            }

            $user = User::query()
                ->whereKey(auth()->id())
                ->lockForUpdate()
                ->firstOrFail();

            $totalPrice = $unitPrice * $qty;

            if ((int) ($user->gold ?? 0) < $totalPrice) {
                throw ValidationException::withMessages([
                    'quantity' => 'Gold tidak cukup untuk membeli item ini.',
                ]);
            }

            $inventory = UserInventory::query()
                ->where('user_id', $user->id)
                ->where('shop_item_id', $lockedItem->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = UserInventory::create([
                    'user_id' => $user->id,
                    'shop_item_id' => $lockedItem->id,
                    'quantity' => 0,
                ]);
            }

            $inventory->increment('quantity', $qty);
            $user->decrement('gold', $totalPrice);

            ShopTransaction::create([
                'user_id' => $user->id,
                'shop_item_id' => $lockedItem->id,
                'type' => 'purchase',
                'quantity' => $qty,
                'gold_change' => -$totalPrice,
                'note' => 'Purchase from user shop',
                'meta' => [
                    'item_code' => $lockedItem->code,
                    'unit_price_gold' => $unitPrice,
                ],
            ]);
        });

        CacheVersion::bump('shop');

        return back()->with('message', 'ITEM_PURCHASE_SUCCESS');
    }
}
