<?php

namespace App\Models;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
   
protected $fillable = ['uuid','title','status', 'description', 'exp_reward', 'reward_gold','difficulty', 'is_completed','study_group_id','deadline'];

protected static function booted()
    {
        static::creating(function ($quest) {
            $quest->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

public function submissions()
{

    return $this->hasMany(Submission::class);
}

public function studyGroup()
{
    return $this->belongsTo(StudyGroup::class);
}

protected $casts = [
    'deadline' => 'datetime',
];



}
