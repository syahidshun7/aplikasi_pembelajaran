<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyGroupStaff extends Model
{
    protected $table = 'study_group_staff';

    protected $fillable = [
        'study_group_id',
        'user_id',
        'role_in_group',
        'permissions',
        'assigned_by',
    ];

    protected $casts = [
        'study_group_id' => 'integer',
        'user_id' => 'integer',
        'assigned_by' => 'integer',
        'permissions' => 'array',
    ];

    public function studyGroup()
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
