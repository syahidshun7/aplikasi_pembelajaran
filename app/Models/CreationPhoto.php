<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreationPhoto extends Model
{
    protected $fillable = [
        'creation_id',
        'path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'url',
    ];

    public function creation()
    {
        return $this->belongsTo(Creation::class);
    }

    public function getUrlAttribute(): string
    {
        $path = trim((string) ($this->path ?? ''));
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
