<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'uuid',
        'title',
        'description',
        'sequence_order',
        'study_group_id',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function studyGroup()
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function guides()
    {
        return $this->belongsToMany(Guide::class, 'event_guide')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('event_guide.sort_order');
    }

    public function quests()
    {
        return $this->belongsToMany(Quest::class, 'event_quest')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('event_quest.sort_order');
    }

    public function attendances()
    {
        return $this->hasMany(EventAttendance::class);
    }
}
