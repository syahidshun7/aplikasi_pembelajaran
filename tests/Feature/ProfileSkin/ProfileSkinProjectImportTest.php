<?php

use App\Models\ProfileSkin;
use App\Models\ShopItem;
use App\Models\User;
use App\Models\UserInventory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('imports a static project folder as a profile skin bundle', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $manifest = json_encode([
        'shop' => [
            'code' => 'SKIN_TEST_PROJECT',
            'name' => 'Skin: Test Project',
            'price_gold' => 700,
            'is_active' => true,
        ],
        'skin' => [
            'name' => 'Test Project',
            'slug' => 'test-project',
            'description' => 'Imported project skin.',
            'template_key' => 'project_static',
            'is_active' => true,
        ],
        'assets' => [
            'preview' => 'assets/preview.png',
        ],
        'project' => [
            'entry' => 'index.html',
        ],
    ], JSON_THROW_ON_ERROR);

    $files = [
        UploadedFile::fake()->createWithContent('skin.json', $manifest),
        UploadedFile::fake()->createWithContent('index.html', '<!doctype html><div id="profile"></div>'),
        UploadedFile::fake()->createWithContent('css/style.css', 'body { background: #05070b; }'),
        UploadedFile::fake()->image('preview.png'),
    ];

    $this->actingAs($admin)
        ->post(route('admin.profile-skins.import-bundle'), [
            'bundle_files' => $files,
            'relative_paths' => [
                'test-project/skin.json',
                'test-project/index.html',
                'test-project/css/style.css',
                'test-project/assets/preview.png',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $skin = ProfileSkin::query()->where('slug', 'test-project')->firstOrFail();

    expect($skin->template_key)->toBe('project_static')
        ->and($skin->project_entry_path)->toBe('profile-skins/test-project/project/index.html')
        ->and($skin->project_root_path)->toBe('profile-skins/test-project/project')
        ->and($skin->shopItem?->code)->toBe('SKIN_TEST_PROJECT');

    Storage::disk('public')->assertExists('profile-skins/test-project/project/index.html');
    Storage::disk('public')->assertExists('profile-skins/test-project/project/css/style.css');
    Storage::disk('public')->assertExists('profile-skins/test-project/preview.png');
});

it('lets a user equip an owned imported profile skin', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $shopItem = ShopItem::query()->create([
        'code' => 'SKIN_EQUIP_PROJECT',
        'name' => 'Skin: Equip Project',
        'price_gold' => 500,
        'is_active' => true,
        'is_stackable' => false,
    ]);

    $skin = ProfileSkin::query()->create([
        'shop_item_id' => $shopItem->id,
        'name' => 'Equip Project',
        'slug' => 'equip-project',
        'template_key' => 'project_static',
        'project_entry_path' => 'profile-skins/equip-project/project/index.html',
        'project_root_path' => 'profile-skins/equip-project/project',
        'hero_gradient' => 'linear-gradient(135deg, #05070b 0%, #111827 100%)',
        'accent_color' => '#22d3ee',
        'border_color' => '#334155',
        'glow_color' => 'rgba(34,211,238,0.2)',
        'stat_panel_bg' => '#0f172a',
        'text_primary' => '#67e8f9',
        'is_active' => true,
    ]);

    UserInventory::query()->create([
        'user_id' => $user->id,
        'shop_item_id' => $shopItem->id,
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('profile.skins.activate', $skin))
        ->assertRedirect();

    expect((int) $user->fresh()->active_profile_skin_id)->toBe((int) $skin->id);
});
