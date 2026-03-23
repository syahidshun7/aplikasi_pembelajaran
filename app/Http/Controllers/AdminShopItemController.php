<?php

namespace App\Http\Controllers;

use App\Models\ShopItem;
use App\Models\ShopTransaction;
use App\Models\UserInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminShopItemController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $items = ShopItem::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->withCount(['inventories', 'transactions'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Shop/Admin/Index', [
            'items' => $items,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function detail(Request $request, ShopItem $item): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['purchase', 'consume_unlock'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $type = (string) ($validated['type'] ?? '');

        $item->loadCount(['inventories', 'transactions']);

        $transactions = ShopTransaction::query()
            ->where('shop_item_id', $item->id)
            ->with([
                'user:id,name,username,email',
                'item:id,name,code',
            ])
            ->when($type !== '', function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('note', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $transactions->through(function (ShopTransaction $tx) {
            $meta = is_array($tx->meta) ? $tx->meta : [];
            $tx->is_cancelled = !empty($meta['admin_cancelled_at']);
            return $tx;
        });

        $purchaseQty = (int) ShopTransaction::query()
            ->where('shop_item_id', $item->id)
            ->where('type', 'purchase')
            ->sum('quantity');

        $consumeQty = (int) ShopTransaction::query()
            ->where('shop_item_id', $item->id)
            ->where('type', 'consume_unlock')
            ->sum('quantity');

        $purchaseGold = abs((int) ShopTransaction::query()
            ->where('shop_item_id', $item->id)
            ->where('type', 'purchase')
            ->sum('gold_change'));

        $uniqueBuyerCount = (int) ShopTransaction::query()
            ->where('shop_item_id', $item->id)
            ->where('type', 'purchase')
            ->distinct('user_id')
            ->count('user_id');

        $activeHolderCount = (int) UserInventory::query()
            ->where('shop_item_id', $item->id)
            ->where('quantity', '>', 0)
            ->count();

        $currentStockOwned = (int) UserInventory::query()
            ->where('shop_item_id', $item->id)
            ->sum('quantity');

        return Inertia::render('Shop/Admin/Detail', [
            'item' => $item,
            'transactions' => $transactions,
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
            'stats' => [
                'purchase_qty' => $purchaseQty,
                'consume_qty' => $consumeQty,
                'purchase_gold' => $purchaseGold,
                'unique_buyer_count' => $uniqueBuyerCount,
                'active_holder_count' => $activeHolderCount,
                'current_stock_owned' => $currentStockOwned,
            ],
        ]);
    }

    public function cancelTransaction(ShopItem $item, ShopTransaction $transaction): RedirectResponse
    {
        if ((int) $transaction->shop_item_id !== (int) $item->id) {
            abort(404);
        }

        if ($transaction->type !== 'purchase') {
            return back()->withErrors([
                'transaction' => 'Hanya transaksi purchase yang bisa dibatalkan.',
            ]);
        }

        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        if (!empty($meta['admin_cancelled_at'])) {
            return back()->withErrors([
                'transaction' => 'Transaksi ini sudah pernah dibatalkan.',
            ]);
        }

        try {
            DB::transaction(function () use ($transaction, $item) {
                $tx = ShopTransaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $meta = is_array($tx->meta) ? $tx->meta : [];
                if (!empty($meta['admin_cancelled_at'])) {
                    throw new \RuntimeException('TRANSACTION_ALREADY_CANCELLED');
                }

                $user = \App\Models\User::query()
                    ->whereKey($tx->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $inventory = UserInventory::query()
                    ->where('user_id', $user->id)
                    ->where('shop_item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                $hasUsageAfterPurchase = ShopTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('shop_item_id', $item->id)
                    ->where('type', 'consume_unlock')
                    ->where('created_at', '>=', $tx->created_at)
                    ->exists();

                if ($hasUsageAfterPurchase) {
                    throw new \RuntimeException('ITEM_ALREADY_USED');
                }

                $qty = max(1, (int) $tx->quantity);
                if (! $inventory || (int) $inventory->quantity < $qty) {
                    throw new \RuntimeException('INSUFFICIENT_ITEM_STOCK');
                }

                $refundGold = max(0, -((int) $tx->gold_change));

                $inventory->decrement('quantity', $qty);
                if ($refundGold > 0) {
                    $user->increment('gold', $refundGold);
                }

                $currentMeta = is_array($tx->meta) ? $tx->meta : [];
                $tx->update([
                    'note' => trim(($tx->note ? $tx->note . ' | ' : '') . 'Admin cancelled & refunded'),
                    'meta' => array_merge($currentMeta, [
                        'admin_cancelled_at' => now()->toDateTimeString(),
                        'admin_cancelled_by' => (int) auth()->id(),
                        'refund_gold' => $refundGold,
                        'refund_quantity' => $qty,
                    ]),
                ]);
            });
        } catch (\RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'ITEM_ALREADY_USED' => 'Transaksi tidak bisa dibatalkan karena item sudah digunakan user.',
                'INSUFFICIENT_ITEM_STOCK' => 'Transaksi tidak bisa dibatalkan karena item sudah berkurang/digunakan.',
                'TRANSACTION_ALREADY_CANCELLED' => 'Transaksi ini sudah pernah dibatalkan.',
                default => 'Pembatalan transaksi gagal.',
            };

            return back()->withErrors([
                'transaction' => $message,
            ]);
        }

        return back()->with('message', 'SHOP_TRANSACTION_CANCELLED_AND_REFUNDED');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:shop_items,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_gold' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('shop-items', 'public');
        }

        ShopItem::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_gold' => (int) $validated['price_gold'],
            'is_active' => (bool) $validated['is_active'],
            'icon_path' => $iconPath,
            'is_stackable' => true,
        ]);

        return back()->with('message', 'SHOP_ITEM_CREATED');
    }

    public function update(Request $request, ShopItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:shop_items,code,' . $item->id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_gold' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $iconPath = $item->icon_path;
        if ($request->hasFile('icon')) {
            if ($iconPath && Storage::disk('public')->exists($iconPath)) {
                Storage::disk('public')->delete($iconPath);
            }
            $iconPath = $request->file('icon')->store('shop-items', 'public');
        }

        $item->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_gold' => (int) $validated['price_gold'],
            'is_active' => (bool) $validated['is_active'],
            'icon_path' => $iconPath,
        ]);

        return back()->with('message', 'SHOP_ITEM_UPDATED');
    }

    public function destroy(ShopItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('message', 'SHOP_ITEM_DELETED');
    }
}
