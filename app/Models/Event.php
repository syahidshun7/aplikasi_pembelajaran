<?php

namespace App\Models;

use App\Support\DateTimeInput;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'sequence_order',
        'study_group_id',
        'job_id',
        'starts_at',
        'ends_at',
        'self_attendance_enabled',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'self_attendance_enabled' => 'boolean',
    ];

    protected function startsAt(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => DateTimeInput::normalizeNullable($value),
        );
    }

    protected function endsAt(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => DateTimeInput::normalizeNullable($value),
        );
    }

    protected static function booted(): void
    {
        static::creating(function ($event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (Event $event) {
            if (! method_exists($event, 'isForceDeleting') || ! $event->isForceDeleting()) {
                return;
            }

            $paths = $event->images()
                ->pluck('path')
                ->filter(fn ($path) => is_string($path) && trim($path) !== '')
                ->values()
                ->all();

            if (! empty($paths)) {
                Storage::disk('public')->delete($paths);
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

    public function job()
    {
        return $this->belongsTo(JobRole::class, 'job_id');
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

    public function images()
    {
        return $this->hasMany(EventImage::class)->orderBy('sort_order');
    }
}
