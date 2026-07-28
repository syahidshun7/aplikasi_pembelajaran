<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guide extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'study_group_id',
        'file_path',
        'google_docs_embed_url',
        'video_embed_url',
    ];

    // Menentukan kolom UUID sebagai pengenal di route (opsional tapi bagus)
   public function uniqueIds(): array
    {
        return ['uuid']; // Laravel akan mengisi kolom 'uuid', bukan 'id'
    }

    /**
     * Agar route binding menggunakan kolom uuid.
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function studyGroup()
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_guide')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
   
}
