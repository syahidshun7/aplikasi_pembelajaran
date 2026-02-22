<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // 1. Tambahkan ini

class StudyGroup extends Model
{
    use HasUuids; // 2. Gunakan ini

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'invite_code',
        'max_members'
    ];

    // 3. Beritahu Laravel kolom mana yang berisi UUID otomatis
    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function quests()
    {
        return $this->hasMany(Quest::class);
    }
}
