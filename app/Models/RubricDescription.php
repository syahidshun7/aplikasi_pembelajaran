<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RubricDescription extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'criteria_id',
        'level_id',
        'description',
    ];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class, 'criteria_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(RubricLevel::class, 'level_id');
    }
}
