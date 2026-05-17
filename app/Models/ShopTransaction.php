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

    protected static function booted(): void
    {
        static::saving(function (self $transaction): void {
            $goldChange = (int) ($transaction->gold_change ?? 0);

            if ((string) $transaction->type === 'purchase') {
                // Purchase must always be an expense in user gold ledger.
                $transaction->gold_change = -abs($goldChange);
                return;
            }

            if ((string) $transaction->type === 'consume_unlock') {
                // Using Time Key consumes inventory only, never changes gold.
                $transaction->gold_change = 0;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function item()
    {
        return $this->belongsTo(ShopItem::class, 'shop_item_id');
    }
}
