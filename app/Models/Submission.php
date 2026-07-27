<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str; // <-- 1. PASTIKAN ADA INI

class Submission extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';

    public const PIPELINE_STATUS_PENDING_PREPROCESSING = 'pending_preprocessing';
    public const PIPELINE_STATUS_PREPROCESSING = 'preprocessing';
    public const PIPELINE_STATUS_PREPROCESSED = 'preprocessed';
    public const PIPELINE_STATUS_CLEANING = 'cleaning';
    public const PIPELINE_STATUS_CLEANED = 'cleaned';
    public const PIPELINE_STATUS_STRUCTURE_DETECTION = 'structure_detection';
    public const PIPELINE_STATUS_STRUCTURED = 'structured';
    public const PIPELINE_STATUS_SEMANTIC_ENRICHMENT = 'semantic_enrichment';
    public const PIPELINE_STATUS_SEMANTIC_ENRICHED = 'semantic_enriched';
    public const PIPELINE_STATUS_RUBRIC_PREPARATION = 'rubric_preparation';
    public const PIPELINE_STATUS_RUBRIC_PREPARED = 'rubric_prepared';
    public const PIPELINE_STATUS_AI_EVALUATION = 'ai_evaluation';
    public const PIPELINE_STATUS_AI_CHECKED = 'ai_checked';
    public const PIPELINE_STATUS_POST_EVALUATION_VALIDATION = 'post_evaluation_validation';
    public const PIPELINE_STATUS_POST_EVALUATION_VALIDATED = 'post_evaluation_validated';
    public const PIPELINE_STATUS_RESULT_PRESENTATION = 'result_presentation';
    public const PIPELINE_STATUS_EVALUATED = 'evaluated';

    protected $fillable = [
        'uuid',
        'submission_id',
        'quest_id',
        'user_id',
        'attempt_number',
        'reward_eligible',
        'client_submission_token',
        'content',
        'status',
        'pipeline_status',
        'preprocess_started',
        'file_type',
        'extracted_text',
        'extraction_result',
        'extracted_at',
        'clean_text',
        'cleaning_result',
        'cleaning_language',
        'cleaned_at',
        'structured_items',
        'structure_result',
        'structure_detected_at',
        'semantic_items',
        'semantic_result',
        'semantic_enriched_at',
        'rubric_preparation_items',
        'rubric_preparation_result',
        'rubric_prepared_at',
        'ai_evaluation_items',
        'ai_evaluation_result',
        'ai_evaluated_at',
        'post_evaluation_validation_items',
        'post_evaluation_validation_result',
        'post_evaluation_validated_at',
        'result_presentation_items',
        'result_presentation_result',
        'result_presented_at',
        'earned_exp',
        'earned_gold',
        'scores_detail',
        'admin_notes',
        'file_path',
        'grade',
        'feedback',
    ];

    protected $casts = [
        'grade' => 'integer',
        'earned_exp' => 'integer',
        'earned_gold' => 'integer',
        'scores_detail' => 'array',
        'preprocess_started' => 'boolean',
        'extraction_result' => 'array',
        'extracted_at' => 'datetime',
        'cleaning_result' => 'array',
        'cleaned_at' => 'datetime',
        'structured_items' => 'array',
        'structure_result' => 'array',
        'structure_detected_at' => 'datetime',
        'semantic_items' => 'array',
        'semantic_result' => 'array',
        'semantic_enriched_at' => 'datetime',
        'rubric_preparation_items' => 'array',
        'rubric_preparation_result' => 'array',
        'rubric_prepared_at' => 'datetime',
        'ai_evaluation_items' => 'array',
        'ai_evaluation_result' => 'array',
        'ai_evaluated_at' => 'datetime',
        'post_evaluation_validation_items' => 'array',
        'post_evaluation_validation_result' => 'array',
        'post_evaluation_validated_at' => 'datetime',
        'result_presentation_items' => 'array',
        'result_presentation_result' => 'array',
        'result_presented_at' => 'datetime',
        'attempt_number' => 'integer',
        'reward_eligible' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($submission) {
            if (empty($submission->uuid)) {
                $submission->uuid = (string) Str::uuid();
            }
        });

        static::created(function ($submission) {
            if (empty($submission->submission_id)) {
                $submission->submission_id = sprintf(
                    'SUB-%s-%03d',
                    now()->format('Ymd'),
                    $submission->id,
                );
                $submission->saveQuietly();
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
