<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
    'quest_id', 
    'user_id', 
    'content', 
    'status', 
    'admin_notes',
    'file_path'
];

public function quest()
{
    return $this->belongsTo(Quest::class);
}

public function user()
{
    return $this->belongsTo(User::class);
}
}
