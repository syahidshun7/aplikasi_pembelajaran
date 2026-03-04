<?php

namespace App\Http\Controllers;

use App\Models\ShopItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            ->withCount('inventories')
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
        if ($item->icon_path && Storage::disk('public')->exists($item->icon_path)) {
            Storage::disk('public')->delete($item->icon_path);
        }

        $item->delete();

        return back()->with('message', 'SHOP_ITEM_DELETED');
    }
}

