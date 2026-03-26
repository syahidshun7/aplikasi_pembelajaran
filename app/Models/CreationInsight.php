<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreationInsight extends Model
{
    protected $fillable = [
        'user_id',
        'creation_id',
        'parent_id',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creation()
    {
        return $this->belongsTo(Creation::class);
    }

    public function parent()
    {
        return $this->belongsTo(CreationInsight::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(CreationInsight::class, 'parent_id')
            ->orderBy('created_at');
    }
}
