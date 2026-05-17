<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreationAppreciation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'creation_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creation()
    {
        return $this->belongsTo(Creation::class);
    }
}
