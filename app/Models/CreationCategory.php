<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreationCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function creations()
    {
        return $this->hasMany(Creation::class, 'category_id');
    }
}
