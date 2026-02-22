<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // <-- 1. PASTIKAN ADA INI

class Submission extends Model
{
    protected $fillable = [
        'uuid', // Tambahkan ini
        'quest_id', 
        'user_id', 
        'content', 
        'status', 
        'admin_notes',
        'file_path',
        'grade',    
        'feedback', 
    ];

    protected static function booted()
    {
        static::creating(function ($submission) {
            // Hanya isi UUID jika belum ada isinya
            if (empty($submission->uuid)) {
                $submission->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // Relasi
    public function quest()
    {
        return $this->belongsTo(Quest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}