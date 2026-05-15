<?php

use App\Models\ShopItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ShopItem::query()->firstOrCreate(
            ['code' => 'dooplab_key'],
            [
                'name' => 'Kunci DoopLab',
                'description' => 'Membuka akses permanen ke fitur DoopLab.',
                'price_gold' => 500,
                'icon_path' => null,
                'is_active' => true,
                'is_stackable' => false,
            ]
        );
    }

    public function down(): void
    {
        ShopItem::query()->where('code', 'dooplab_key')->delete();
    }
};
