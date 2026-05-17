<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreationReviewPublication extends Model
{
    protected $fillable = [
        'creation_id',
        'peer_review_id',
        'official_review_id',
        'published_by',
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];

    public function creation()
    {
        return $this->belongsTo(Creation::class);
    }

    public function peerReview()
    {
        return $this->belongsTo(CreationPeerReview::class, 'peer_review_id');
    }

    public function officialReview()
    {
        return $this->belongsTo(CreationReview::class, 'official_review_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}

