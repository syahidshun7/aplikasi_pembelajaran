<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCheckInCode extends Model
{
    protected $fillable = [
        'event_id',
        'code_hash',
        'plain_code_last_four',
        'qr_token',
        'expires_at',
        'created_by_user_id',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
