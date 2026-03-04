<?php

namespace App\Http\Controllers;

use App\Models\ShopItem;
use App\Models\ShopTransaction;
use App\Models\User;
use App\Models\UserInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ShopController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $items = ShopItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

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
            $user = User::query()
                ->whereKey(auth()->id())
                ->lockForUpdate()
                ->firstOrFail();

            $totalPrice = (int) $item->price_gold * $qty;

            if ((int) ($user->gold ?? 0) < $totalPrice) {
                throw ValidationException::withMessages([
                    'quantity' => 'Gold tidak cukup untuk membeli item ini.',
                ]);
            }

            $inventory = UserInventory::query()
                ->where('user_id', $user->id)
                ->where('shop_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = UserInventory::create([
                    'user_id' => $user->id,
                    'shop_item_id' => $item->id,
                    'quantity' => 0,
                ]);
            }

            $inventory->increment('quantity', $qty);
            $user->decrement('gold', $totalPrice);

            ShopTransaction::create([
                'user_id' => $user->id,
                'shop_item_id' => $item->id,
                'type' => 'purchase',
                'quantity' => $qty,
                'gold_change' => -$totalPrice,
                'note' => 'Purchase from user shop',
                'meta' => [
                    'item_code' => $item->code,
                    'unit_price_gold' => (int) $item->price_gold,
                ],
            ]);
        });

        return back()->with('message', 'ITEM_PURCHASE_SUCCESS');
    }
}

