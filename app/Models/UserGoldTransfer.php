<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGoldTransfer extends Model
{
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'amount',
        'status',
        'note',
        'meta',
    ];

    protected $casts = [
        'amount' => 'integer',
        'meta' => 'array',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
