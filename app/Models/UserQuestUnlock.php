<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserQuestUnlock extends Model
{
    protected $fillable = [
        'user_id',
        'quest_id',
        'shop_item_id',
        'unlocked_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quest()
    {
        return $this->belongsTo(Quest::class);
    }

    public function item()
    {
        return $this->belongsTo(ShopItem::class, 'shop_item_id');
    }
}

