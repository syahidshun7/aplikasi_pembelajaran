<?php

namespace Database\Seeders;

use App\Models\ProfileSkin;
use App\Models\ShopItem;
use Illuminate\Database\Seeder;

class ProfileSkinSeeder extends Seeder
{
    public function run(): void
    {
        $skins = [
            [
                'shop' => [
                    'code' => 'SKIN_VOID_PHANTOM',
                    'name' => 'Skin: Void Phantom',
                    'description' => 'Profile skin bertema kegelapan dengan aksen ungu misterius.',
                    'price_gold' => 500,
                    'is_active' => true,
                    'is_stackable' => false,
                ],
                'skin' => [
                    'name' => 'Void Phantom',
                    'slug' => 'void-phantom',
                    'description' => 'Kegelapan yang menelan segalanya. Skin bertema void dengan kilauan ungu.',
                    'template_key' => 'void_phantom',
                    'hero_gradient' => 'linear-gradient(135deg, #0d0015 0%, #1a0a2e 50%, #0d1117 100%)',
                    'accent_color' => '#a855f7',
                    'border_color' => '#7c3aed',
                    'glow_color' => 'rgba(168,85,247,0.35)',
                    'stat_panel_bg' => '#130d1f',
                    'text_primary' => '#c4b5fd',
                ],
            ],
            [
                'shop' => [
                    'code' => 'SKIN_NEON_ARCADE',
                    'name' => 'Skin: Neon Arcade Cabinet',
                    'description' => 'Cosmetic profile skin dengan UI arcade cabinet, neon bezel, dan asset pack penuh.',
                    'price_gold' => 650,
                    'icon_path' => 'profile-skins/neon-arcade/preview.png',
                    'is_active' => true,
                    'is_stackable' => false,
                ],
                'skin' => [
                    'name' => 'Neon Arcade Cabinet',
                    'slug' => 'neon-arcade-cabinet',
                    'description' => 'Profil publik berubah menjadi mesin arcade neon lengkap dengan background, panel, frame avatar, dan dekorasi.',
                    'preview_image_path' => 'profile-skins/neon-arcade/preview.png',
                    'template_key' => 'arcade_cabinet',
                    'background_image_path' => 'profile-skins/neon-arcade/background.png',
                    'avatar_frame_image_path' => 'profile-skins/neon-arcade/avatar-frame.png',
                    'panel_image_path' => 'profile-skins/neon-arcade/panel.png',
                    'decoration_image_path' => 'profile-skins/neon-arcade/decoration.png',
                    'hero_gradient' => 'linear-gradient(135deg, #030712 0%, #1e1b4b 48%, #0f172a 100%)',
                    'accent_color' => '#22d3ee',
                    'border_color' => '#f472b6',
                    'glow_color' => 'rgba(34,211,238,0.42)',
                    'stat_panel_bg' => '#070b16',
                    'text_primary' => '#67e8f9',
                ],
            ],
        ];

        foreach ($skins as $data) {
            $shopItem = ShopItem::updateOrCreate(
                ['code' => $data['shop']['code']],
                $data['shop']
            );

            ProfileSkin::updateOrCreate(
                ['slug' => $data['skin']['slug']],
                array_merge($data['skin'], ['shop_item_id' => $shopItem->id])
            );
        }
    }
}
