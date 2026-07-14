<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabRoadmapEnrollment extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';
    public const REVIEW_MODE_MANUAL = 'manual';
    public const REVIEW_MODE_AUTO = 'auto';

    protected $table = 'dooplab_roadmap_enrollments';

    protected $fillable = [
        'uuid',
        'roadmap_id',
        'user_id',
        'mentor_user_id',
        'status',
        'review_mode',
    ];

    public function isAutoReview(): bool
    {
        return $this->review_mode === self::REVIEW_MODE_AUTO;
    }

    protected static function booted(): void
    {
        static::creating(function (self $enrollment): void {
            if (empty($enrollment->uuid)) {
                $enrollment->uuid = (string) Str::uuid();
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function nodeProgress()
    {
        return $this->hasMany(DoopLabRoadmapNodeProgress::class, 'enrollment_id');
    }
}
