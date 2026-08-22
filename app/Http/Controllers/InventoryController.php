<?php

namespace App\Http\Controllers;

use App\Models\UserInventory;
use App\Models\UserInventoryLog;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $activeProfileSkinId = (int) ($user->active_profile_skin_id ?? 0);

        $inventoryQuery = UserInventory::query()
            ->with('item.profileSkin:id,shop_item_id,slug,name,description,renderer_type,template_key,preview_image_path,background_image_path,avatar_frame_image_path,panel_image_path,decoration_image_path,project_entry_path,project_root_path,config_json,hero_gradient,accent_color,border_color,glow_color,stat_panel_bg,text_primary')
            ->where('user_id', (int) $user->id)
            ->where('quantity', '>', 0);

        $summary = [
            'unique_items' => (int) (clone $inventoryQuery)->count(),
            'total_quantity' => (int) (clone $inventoryQuery)->sum('quantity'),
        ];

        $inventories = $inventoryQuery
            ->orderByDesc('updated_at')
            ->paginate(12, ['*'], 'items_page')
            ->withQueryString()
            ->through(function (UserInventory $inventory) use ($activeProfileSkinId) {
                $item = $inventory->item;
                $code = (string) ($item?->code ?? '');
                $profileSkin = $item?->profileSkin;

                return [
                    'id' => (int) $inventory->id,
                    'quantity' => (int) ($inventory->quantity ?? 0),
                    'updated_at' => $inventory->updated_at?->toIso8601String(),
                    'item' => [
                        'id' => (int) ($item?->id ?? 0),
                        'code' => $code,
                        'name' => (string) ($item?->name ?? 'Unknown Item'),
                        'description' => (string) ($item?->description ?? ''),
                        'price_gold' => (int) ($item?->price_gold ?? 0),
                        'icon_path' => (string) ($item?->icon_path ?? ''),
                        'is_active' => (bool) ($item?->is_active ?? false),
                        'item_kind' => $profileSkin ? 'profile_skin' : 'item',
                        'profile_skin' => $profileSkin ? [
                            'id' => (int) $profileSkin->id,
                            'name' => (string) $profileSkin->name,
                            'slug' => (string) $profileSkin->slug,
                            'template_key' => (string) ($profileSkin->template_key ?? 'default'),
                            'renderer_type' => (string) ($profileSkin->renderer_type ?? ($profileSkin->project_entry_path ? 'project_static' : 'vue_template')),
                            'preview_image_path' => (string) ($profileSkin->preview_image_path ?? ''),
                            'background_image_path' => (string) ($profileSkin->background_image_path ?? ''),
                            'avatar_frame_image_path' => (string) ($profileSkin->avatar_frame_image_path ?? ''),
                            'panel_image_path' => (string) ($profileSkin->panel_image_path ?? ''),
                            'decoration_image_path' => (string) ($profileSkin->decoration_image_path ?? ''),
                            'project_entry_path' => (string) ($profileSkin->project_entry_path ?? ''),
                            'project_root_path' => (string) ($profileSkin->project_root_path ?? ''),
                            'config_json' => $profileSkin->config_json,
                            'hero_gradient' => (string) ($profileSkin->hero_gradient ?? ''),
                            'accent_color' => (string) ($profileSkin->accent_color ?? ''),
                            'border_color' => (string) ($profileSkin->border_color ?? ''),
                            'glow_color' => (string) ($profileSkin->glow_color ?? ''),
                            'stat_panel_bg' => (string) ($profileSkin->stat_panel_bg ?? ''),
                            'text_primary' => (string) ($profileSkin->text_primary ?? ''),
                            'is_equipped' => $activeProfileSkinId === (int) $profileSkin->id,
                        ] : null,
                        'is_usable' => in_array($code, ['TIME_KEY', 'RETAKE_TICKET'], true),
                        'use_hint' => $profileSkin
                            ? 'Cosmetic skin untuk profil publik. Equip langsung dari Inventory.'
                            : match ($code) {
                                'TIME_KEY' => 'Gunakan dari halaman quest yang sudah melewati deadline.',
                                'RETAKE_TICKET' => 'Gunakan dari halaman quest Approved atau Rejected setelah attempt normal habis.',
                                default => 'Item ini belum punya aksi langsung.',
                            },
                    ],
                ];
            });

        $logs = UserInventoryLog::query()
            ->with('item:id,code,name,icon_path')
            ->where('user_id', (int) $user->id)
            ->latest()
            ->paginate(10, ['*'], 'logs_page')
            ->withQueryString()
            ->through(function (UserInventoryLog $log) {
                return [
                    'id' => (int) $log->id,
                    'type' => (string) $log->type,
                    'quantity_change' => (int) ($log->quantity_change ?? 0),
                    'quantity_before' => (int) ($log->quantity_before ?? 0),
                    'quantity_after' => (int) ($log->quantity_after ?? 0),
                    'note' => (string) ($log->note ?? ''),
                    'created_at' => $log->created_at?->toIso8601String(),
                    'item' => [
                        'id' => (int) ($log->item?->id ?? 0),
                        'code' => (string) ($log->item?->code ?? ''),
                        'name' => (string) ($log->item?->name ?? 'Unknown Item'),
                        'icon_path' => (string) ($log->item?->icon_path ?? ''),
                    ],
                ];
            });

        return Inertia::render('Inventory/Index', [
            'inventories' => $inventories,
            'logs' => $logs,
            'summary' => $summary,
        ]);
    }
}
