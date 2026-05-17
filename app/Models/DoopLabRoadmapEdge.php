<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabRoadmapEdge extends Model
{
    protected $table = 'dooplab_roadmap_edges';

    protected $fillable = [
        'uuid',
        'roadmap_id',
        'from_node_id',
        'to_node_id',
        'stroke_color',
        'curvature',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $edge): void {
            if (empty($edge->uuid)) {
                $edge->uuid = (string) Str::uuid();
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

    public function fromNode()
    {
        return $this->belongsTo(DoopLabRoadmapNode::class, 'from_node_id');
    }

    public function toNode()
    {
        return $this->belongsTo(DoopLabRoadmapNode::class, 'to_node_id');
    }
}

