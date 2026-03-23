<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RubricCriterion extends Model
{
    use SoftDeletes;

    protected $table = 'rubric_criteria';

    public $timestamps = false;

    protected $fillable = [
        'rubric_id',
        'name',
        'weight',
        'order',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'order' => 'integer',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubric_id');
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(RubricDescription::class, 'criteria_id');
    }
}
