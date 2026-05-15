<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabRoadmapNode extends Model
{
    protected $table = 'dooplab_roadmap_nodes';

    protected $fillable = [
        'uuid',
        'roadmap_id',
        'section_id',
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
        'resource_type',
        'resource_id',
        'sort_order',
    ];

    public function resourceGuide()
    {
        return $this->belongsTo(Guide::class, 'resource_id')->where('resource_type', 'guide');
    }

    public function resourceQuest()
    {
        return $this->belongsTo(Quest::class, 'resource_id')->where('resource_type', 'quest');
    }

    protected static function booted(): void
    {
        static::creating(function (self $node): void {
            if (empty($node->uuid)) {
                $node->uuid = (string) Str::uuid();
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

    public function section()
    {
        return $this->belongsTo(DoopLabRoadmapSection::class, 'section_id');
    }

    public function outgoingEdges()
    {
        return $this->hasMany(DoopLabRoadmapEdge::class, 'from_node_id');
    }

    public function incomingEdges()
    {
        return $this->hasMany(DoopLabRoadmapEdge::class, 'to_node_id');
    }

    public function resources()
    {
        return $this->hasMany(DoopLabRoadmapNodeResource::class, 'node_id')->orderBy('sort_order')->orderBy('id');
    }
}
