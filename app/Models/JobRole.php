<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobRole extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'emblem_path',
    ];

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
