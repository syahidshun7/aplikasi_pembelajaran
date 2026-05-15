<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoopLabRoadmapNodeResource extends Model
{
    protected $table = 'dooplab_roadmap_node_resources';

    protected $fillable = [
        'node_id',
        'resource_type',
        'resource_id',
        'sort_order',
    ];

    public function node()
    {
        return $this->belongsTo(DoopLabRoadmapNode::class, 'node_id');
    }
}

