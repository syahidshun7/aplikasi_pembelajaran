<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    // Model ini terhubung ke tabel apa?
    protected $table = 'projects';

    // Kolom yang boleh diisi secara massal
    protected $fillable = [
        'title',
        'description',
        'image',
    ];

    // Primary key (default sebenarnya sudah 'id')
    protected $primaryKey = 'id';

    // Apakah pakai created_at & updated_at
    public $timestamps = true;
}


