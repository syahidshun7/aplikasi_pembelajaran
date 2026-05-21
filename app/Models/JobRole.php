<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobRole extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMING_SOON = 'coming_soon';
    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'emblem_path',
        'status',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_COMING_SOON,
            self::STATUS_DRAFT,
        ];
    }

    public function scopePublicVisible($query)
    {
        return $query->whereIn('status', [
            self::STATUS_ACTIVE,
            self::STATUS_COMING_SOON,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'job_id');
    }

    public function studyGroups()
    {
        return $this->hasMany(StudyGroup::class, 'job_id');
    }

    public function taskBanks()
    {
        return $this->hasMany(TaskBank::class, 'job_role_id');
    }
}
