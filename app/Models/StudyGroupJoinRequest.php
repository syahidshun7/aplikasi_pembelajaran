<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyGroupJoinRequest extends Model
{
    protected $fillable = [
        'study_group_id',
        'user_id',
        'status',
        'reason',
        'processed_by',
    ];

    public function studyGroup()
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
