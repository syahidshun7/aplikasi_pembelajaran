<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabRoadmap extends Model
{
    protected $table = 'dooplab_roadmaps';

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'is_published',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $roadmap): void {
            if (empty($roadmap->uuid)) {
                $roadmap->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function sections()
    {
        return $this->hasMany(DoopLabRoadmapSection::class, 'roadmap_id');
    }

    public function nodes()
    {
        return $this->hasMany(DoopLabRoadmapNode::class, 'roadmap_id');
    }

    public function edges()
    {
        return $this->hasMany(DoopLabRoadmapEdge::class, 'roadmap_id');
    }
}

