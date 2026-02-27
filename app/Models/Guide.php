<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Guide extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'description', 'study_group_id', 'file_path'];

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
   
}
