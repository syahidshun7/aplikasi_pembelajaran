<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabRoadmapSection extends Model
{
    protected $table = 'dooplab_roadmap_sections';

    protected $fillable = [
        'uuid',
        'roadmap_id',
        'title',
        'x',
        'y',
        'width',
        'height',
        'bg_color',
        'text_color',
        'font_size',
        'text_align',
        'text_valign',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $section): void {
            if (empty($section->uuid)) {
                $section->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function roadmap()
    {
        return $this->belongsTo(DoopLabRoadmap::class, 'roadmap_id');
    }

    public function nodes()
    {
        return $this->hasMany(DoopLabRoadmapNode::class, 'section_id');
    }
}
