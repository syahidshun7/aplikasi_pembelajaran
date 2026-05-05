<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreationReview extends Model
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_NEEDS_REVISION = 'needs_revision';

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
        'source_peer_review_id',
        'published_by',
        'published_at',
        'reviewed_at',
    ];

    protected $casts = [
        'score_percent' => 'integer',
        'selected_levels' => 'array',
        'result_breakdown' => 'array',
        'rubric_snapshot' => 'array',
        'published_at' => 'datetime',
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
        return $this->belongsTo(Rubric::class, 'rubric_id');
    }

    public function sourcePeerReview()
    {
        return $this->belongsTo(CreationPeerReview::class, 'source_peer_review_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
