<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
   
protected $fillable = ['title','status', 'description', 'xp_reward', 'reward_gold','difficulty', 'is_completed'];

public function submissions()
{
    return $this->hasMany(Submission::class);
}
}
