<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserQuestAttemptUnlock extends Model
{
    protected $fillable = [
        'user_id',
        'quest_id',
        'shop_item_id',
        'attempt_number',
        'unlocked_at',
        'used_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'unlocked_at' => 'datetime',
        'used_at' => 'datetime',
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
