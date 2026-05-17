<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RubricLevel extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'rubric_id',
        'level',
        'label',
        'score_value',
    ];

    protected $casts = [
        'level' => 'integer',
        'score_value' => 'decimal:2',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubric_id');
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(RubricDescription::class, 'level_id');
    }
}
