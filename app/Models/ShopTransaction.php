<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'shop_item_id',
        'type',
        'quantity',
        'gold_change',
        'note',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'gold_change' => 'integer',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function item()
    {
        return $this->belongsTo(ShopItem::class, 'shop_item_id');
    }
}

