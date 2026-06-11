<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfileSkin extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shop_item_id',
        'name',
        'slug',
        'description',
        'preview_image_path',
        'renderer_type',
        'template_key',
        'background_image_path',
        'avatar_frame_image_path',
        'panel_image_path',
        'decoration_image_path',
        'bundle_root_path',
        'project_entry_path',
        'project_root_path',
        'project_manifest',
        'config_json',
        'hero_gradient',
        'accent_color',
        'border_color',
        'glow_color',
        'stat_panel_bg',
        'text_primary',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'project_manifest' => 'array',
        'config_json' => 'array',
    ];

    public function shopItem()
    {
        return $this->belongsTo(ShopItem::class);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'active_profile_skin_id');
    }

    /**
     * Check if a user owns this skin (via UserInventory of linked shop item).
     */
    public function isOwnedBy(User $user): bool
    {
        if (! $this->shop_item_id) {
            return false;
        }

        return UserInventory::query()
            ->where('user_id', $user->id)
            ->where('shop_item_id', $this->shop_item_id)
            ->where('quantity', '>=', 1)
            ->exists();
    }

    public function toThemeArray(): array
    {
        $rendererType = $this->renderer_type ?: ($this->project_entry_path ? 'project_static' : 'vue_template');
        if ($this->project_entry_path && ($this->template_key ?: 'default') === 'project_static') {
            $rendererType = 'project_static';
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'preview_image_path' => $this->preview_image_path,
            'renderer_type' => $rendererType,
            'template_key' => $this->template_key ?: 'default',
            'background_image_path' => $this->background_image_path,
            'avatar_frame_image_path' => $this->avatar_frame_image_path,
            'panel_image_path' => $this->panel_image_path,
            'decoration_image_path' => $this->decoration_image_path,
            'bundle_root_path' => $this->bundle_root_path,
            'project_entry_path' => $this->project_entry_path,
            'project_root_path' => $this->project_root_path,
            'project_manifest' => $this->project_manifest,
            'config_json' => $this->config_json,
            'hero_gradient' => $this->hero_gradient,
            'accent_color' => $this->accent_color,
            'border_color' => $this->border_color,
            'glow_color' => $this->glow_color,
            'stat_panel_bg' => $this->stat_panel_bg,
            'text_primary' => $this->text_primary,
        ];
    }
}
