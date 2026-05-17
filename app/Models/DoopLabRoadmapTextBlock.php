<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabRoadmapTextBlock extends Model
{
    protected $table = 'dooplab_roadmap_text_blocks';

    protected $fillable = [
        'uuid',
        'roadmap_id',
        'content',
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
        static::creating(function (self $textBlock): void {
            if (empty($textBlock->uuid)) {
                $textBlock->uuid = (string) Str::uuid();
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
}
