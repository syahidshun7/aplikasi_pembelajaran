<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rubric extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'mentor_id',
        'max_score',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class, 'rubric_id')->orderBy('order');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(RubricLevel::class, 'rubric_id')->orderBy('level');
    }

    public function descriptions(): HasManyThrough
    {
        return $this->hasManyThrough(
            RubricDescription::class,
            RubricCriterion::class,
            'rubric_id',
            'criteria_id',
            'id',
            'id'
        );
    }

    public function exportAsJson(): array
    {
        $this->loadMissing([
            'criteria',
            'levels',
            'descriptions',
        ]);

        return [
            'rubric' => [
                'id' => $this->id,
                'title' => $this->title,
                'description' => $this->description,
                'mentor_id' => $this->mentor_id,
                'max_score' => (float) $this->max_score,
            ],
            'criteria' => $this->criteria->map(fn (RubricCriterion $c) => [
                'id' => $c->id,
                'rubric_id' => $c->rubric_id,
                'name' => $c->name,
                'weight' => (float) $c->weight,
                'order' => $c->order,
            ])->values()->all(),
            'levels' => $this->levels->map(fn (RubricLevel $l) => [
                'id' => $l->id,
                'rubric_id' => $l->rubric_id,
                'level' => $l->level,
                'label' => $l->label,
                'score_value' => (float) $l->score_value,
            ])->values()->all(),
            'matrix' => $this->descriptions->map(fn (RubricDescription $d) => [
                'criteria_id' => $d->criteria_id,
                'level_id' => $d->level_id,
                'description' => $d->description,
            ])->values()->all(),
        ];
    }
}
