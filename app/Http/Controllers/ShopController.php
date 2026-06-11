<?php

namespace App\Http\Controllers;

use App\Models\ShopItem;
use App\Models\ShopTransaction;
use App\Models\User;
use App\Models\UserGoldTransfer;
use App\Models\UserInventory;
use App\Models\UserInventoryLog;
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
                ->with('profileSkin:id,shop_item_id,slug,name,template_key,preview_image_path,background_image_path')
                ->where('is_active', true)
                ->orderBy('name')
                ->select([
                    'id',
                    'code',
                    'name',
                    'description',
                    'price_gold',
                    'icon_path',
                    'is_active',
                    'is_stackable',
                ])
                ->get()
        );

        $inventories = UserInventory::query()
            ->where('user_id', $user->id)
            ->pluck('quantity', 'shop_item_id');

        $items = $items->map(function ($item) use ($inventories) {
            $item->owned_qty = (int) ($inventories[$item->id] ?? 0);
            $item->item_kind = $item->profileSkin ? 'profile_skin' : 'item';
            $item->profile_skin = $item->profileSkin ? [
                'id' => (int) $item->profileSkin->id,
                'name' => (string) $item->profileSkin->name,
                'slug' => (string) $item->profileSkin->slug,
                'template_key' => (string) ($item->profileSkin->template_key ?? 'default'),
                'preview_image_path' => (string) ($item->profileSkin->preview_image_path ?? ''),
                'background_image_path' => (string) ($item->profileSkin->background_image_path ?? ''),
            ] : null;
            unset($item->profileSkin);

            return $item;
        });

        return Inertia::render('Shop/Index', [
            'items' => $items,
            'gold' => (int) ($user->gold ?? 0),
        ]);
    }

    public function transfer(Request $request)
    {
        if ((bool) $request->user()?->isStaffPlayMode()) {
            throw ValidationException::withMessages([
                'amount' => 'Staff play mode tidak memakai economy utama. Transfer gold dinonaktifkan untuk mentor/admin.',
            ]);
        }

        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'current_password'],
        ]);

        $senderId = (int) $request->user()->id;
        $recipientId = (int) $validated['recipient_id'];

        if ($senderId === $recipientId) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Kamu tidak bisa transfer gold ke akun sendiri.',
            ]);
        }

        DB::transaction(function () use ($senderId, $recipientId, $validated, $request) {
            $lockedUsers = User::query()
                ->whereIn('id', [$senderId, $recipientId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sender = $lockedUsers->get($senderId);
            $recipient = $lockedUsers->get($recipientId);

            if (! $sender || ! $recipient) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'User tujuan tidak ditemukan.',
                ]);
            }

            if ($recipient->isStaff()) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'Transfer ke akun staff/admin tidak tersedia.',
                ]);
            }

            $amount = (int) $validated['amount'];

            if ((int) ($sender->gold ?? 0) < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Gold kamu tidak cukup untuk transfer ini.',
                ]);
            }

            $sender->decrement('gold', $amount);
            $recipient->increment('gold', $amount);

            UserGoldTransfer::query()->create([
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'amount' => $amount,
                'status' => UserGoldTransfer::STATUS_COMPLETED,
                'note' => trim((string) ($validated['note'] ?? '')) ?: null,
                'meta' => [
                    'context' => 'shop.gold.transfer',
                    'sender_gold_before' => (int) ($sender->gold ?? 0),
                    'recipient_gold_before' => (int) ($recipient->gold ?? 0),
                    'ip' => (string) $request->ip(),
                ],
            ]);
        });

        return back()->with('message', 'GOLD_TRANSFER_SUCCESS');
    }

    public function purchase(Request $request, ShopItem $item)
    {
        if (! $item->is_active) {
            abort(404);
        }

        if ((bool) $request->user()?->isStaffPlayMode()) {
            throw ValidationException::withMessages([
                'quantity' => 'Staff play mode tidak memakai economy utama. Pembelian shop dinonaktifkan untuk mentor/admin.',
            ]);
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

            $quantityBefore = (int) ($inventory->quantity ?? 0);
            if (! $lockedItem->is_stackable && ($quantityBefore > 0 || $qty > 1)) {
                throw ValidationException::withMessages([
                    'quantity' => 'Item kosmetik ini sudah kamu miliki. Buka Inventory/Profile untuk memakainya.',
                ]);
            }

            $quantityAfter = $quantityBefore + $qty;

            $inventory->increment('quantity', $qty);
            $user->decrement('gold', $totalPrice);

            $transaction = ShopTransaction::create([
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

            UserInventoryLog::query()->create([
                'user_id' => $user->id,
                'shop_item_id' => $lockedItem->id,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'quantity_change' => $qty,
                'type' => UserInventoryLog::TYPE_PURCHASE,
                'reference_type' => ShopTransaction::class,
                'reference_id' => (int) $transaction->id,
                'note' => 'Purchase from user shop',
                'meta' => [
                    'item_code' => $lockedItem->code,
                    'unit_price_gold' => $unitPrice,
                    'total_price_gold' => $totalPrice,
                ],
            ]);
        });

        CacheVersion::bump('shop');

        return back()->with('message', 'ITEM_PURCHASE_SUCCESS');
    }
}
