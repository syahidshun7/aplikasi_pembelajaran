<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TaskQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'task_bank_id',
        'question_text',
        'question_type',
        'options_json',
        'answer_key',
        'weight',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'options_json' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskQuestion $question) {
            if (! $question->uuid) {
                $question->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function taskBank()
    {
        return $this->belongsTo(TaskBank::class);
    }
}
