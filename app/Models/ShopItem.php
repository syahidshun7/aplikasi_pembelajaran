<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'price_gold',
        'icon_path',
        'is_active',
        'is_stackable',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_stackable' => 'boolean',
        'price_gold' => 'integer',
    ];

    public function inventories()
    {
        return $this->hasMany(UserInventory::class);
    }

    public function transactions()
    {
        return $this->hasMany(ShopTransaction::class, 'shop_item_id');
    }
}
