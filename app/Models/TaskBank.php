<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TaskBank extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'job_role_id',
        'rubric_id',
        'assessment_type',
        'duration',
        'has_time_limit',
        'question_display_mode',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_time_limit' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskBank $bank) {
            if (! $bank->uuid) {
                $bank->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function jobRole()
    {
        return $this->belongsTo(JobRole::class, 'job_role_id');
    }

    public function rubric()
    {
        return $this->belongsTo(Rubric::class, 'rubric_id');
    }

    public function questions()
    {
        return $this->hasMany(TaskQuestion::class);
    }

    public function quests()
    {
        return $this->hasMany(Quest::class);
    }
}
