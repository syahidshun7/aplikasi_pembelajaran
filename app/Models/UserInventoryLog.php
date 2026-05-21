<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInventoryLog extends Model
{
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_USE = 'use';
    public const TYPE_REFUND_REMOVE = 'refund_remove';

    protected $fillable = [
        'user_id',
        'shop_item_id',
        'quantity_before',
        'quantity_after',
        'quantity_change',
        'type',
        'reference_type',
        'reference_id',
        'note',
        'meta',
    ];

    protected $casts = [
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'quantity_change' => 'integer',
        'reference_id' => 'integer',
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
