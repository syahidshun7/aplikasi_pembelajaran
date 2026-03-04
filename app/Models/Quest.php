<?php

namespace App\Models;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
   
protected $fillable = ['uuid','title','status', 'description', 'reward_exp', 'reward_gold','difficulty', 'is_completed','study_group_id','task_bank_id','deadline'];

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

public function taskBank()
{
    return $this->belongsTo(TaskBank::class);
}

public function events()
{
    return $this->belongsToMany(Event::class, 'event_quest')
        ->withPivot('sort_order')
        ->withTimestamps();
}

protected $casts = [
    'deadline' => 'datetime',
];



}
