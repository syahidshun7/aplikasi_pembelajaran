<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Creation extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'link',
        'category',
        'status',
        'progress',
        'is_public',
    ];

    protected $casts = [
        'progress' => 'integer',
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Creation $creation) {
            $paths = $creation->photos()
                ->pluck('path')
                ->filter(fn ($path) => is_string($path) && trim($path) !== '')
                ->values()
                ->all();

            if (empty($paths)) {
                return;
            }

            Storage::disk('public')->delete($paths);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function photos()
    {
        return $this->hasMany(CreationPhoto::class)->orderBy('sort_order');
    }

    public function appreciations()
    {
        return $this->hasMany(CreationAppreciation::class);
    }

    public function insights()
    {
        return $this->hasMany(CreationInsight::class);
    }

    public function topLevelInsights()
    {
        return $this->insights()->whereNull('parent_id');
    }

    public function scopePublicVisible($query)
    {
        return $query->where('is_public', true);
    }
}
