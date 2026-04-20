<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGoldAdjustment extends Model
{
    protected $fillable = [
        'user_id',
        'admin_user_id',
        'gold_before',
        'gold_after',
        'gold_change',
        'reason',
        'meta',
    ];

    protected $casts = [
        'gold_before' => 'integer',
        'gold_after' => 'integer',
        'gold_change' => 'integer',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
