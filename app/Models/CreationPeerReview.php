<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreationPeerReview extends Model
{
    protected $fillable = [
        'creation_id',
        'reviewer_id',
        'rubric_id',
        'score_percent',
        'status',
        'feedback',
        'selected_levels',
        'result_breakdown',
        'rubric_snapshot',
        'reviewed_at',
    ];

    protected $casts = [
        'score_percent' => 'integer',
        'selected_levels' => 'array',
        'result_breakdown' => 'array',
        'rubric_snapshot' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function creation()
    {
        return $this->belongsTo(Creation::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function rubric()
    {
        return $this->belongsTo(Rubric::class);
    }
}
