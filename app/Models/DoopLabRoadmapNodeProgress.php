<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoopLabRoadmapNodeProgress extends Model
{
    public const STATUS_LOCKED = 'locked';
    public const STATUS_UNLOCKED = 'unlocked';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION = 'revision';
    public const STATUS_APPROVED = 'approved';

    protected $table = 'dooplab_roadmap_node_progress';

    protected $fillable = [
        'enrollment_id',
        'node_id',
        'status',
        'student_note',
        'mentor_note',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function enrollment()
    {
        return $this->belongsTo(DoopLabRoadmapEnrollment::class, 'enrollment_id');
    }

    public function node()
    {
        return $this->belongsTo(DoopLabRoadmapNode::class, 'node_id');
    }
}

