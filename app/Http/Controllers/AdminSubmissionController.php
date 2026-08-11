<?php

namespace App\Http\Controllers;

use App\Models\Rubric;
use App\Models\RubricDescription;
use App\Models\Submission;
use App\Models\User;
use App\Services\Ai\SubmissionAiAdvisorService;
use App\Services\LmsNotificationService;
use App\Services\RubricScoringService;
use App\Services\Submissions\CleanSubmissionTextService;
use App\Services\Submissions\DetectSubmissionStructureService;
use App\Services\Submissions\EnrichSubmissionSemanticsService;
use App\Services\Submissions\EvaluateSubmissionAnswersService;
use App\Services\Submissions\PrepareSubmissionRubricService;
use App\Services\Submissions\RawSubmissionExtractionService;
use App\Services\Submissions\PresentSubmissionResultService;
use App\Services\Submissions\ValidatePostEvaluationResultService;
use App\Services\UserRewardSyncService;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class AdminSubmissionController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum mengelola submission.';

    /**
     * Halaman inspeksi detail untuk satu submission.
     */
    public function inspect(Submission $submission)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_STRUCTURE_DETECTION) {
            $structureResult = is_array($submission->structure_result) ? $submission->structure_result : [];
            $structureStatus = (string) ($structureResult['structure_detection_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($structureStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_STRUCTURED
                    : Submission::PIPELINE_STATUS_CLEANED,
            ]);

            $submission = $submission->fresh();
        }

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_SEMANTIC_ENRICHMENT) {
            $semanticResult = is_array($submission->semantic_result) ? $submission->semantic_result : [];
            $semanticStatus = (string) ($semanticResult['semantic_enrichment_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($semanticStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED
                    : Submission::PIPELINE_STATUS_STRUCTURED,
            ]);

            $submission = $submission->fresh();
        }

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_RUBRIC_PREPARATION) {
            $rubricPreparationResult = is_array($submission->rubric_preparation_result) ? $submission->rubric_preparation_result : [];
            $rubricPreparationStatus = (string) ($rubricPreparationResult['rubric_preparation_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($rubricPreparationStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_RUBRIC_PREPARED
                    : Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
            ]);

            $submission = $submission->fresh();
        }

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_AI_EVALUATION) {
            $aiEvaluationResult = is_array($submission->ai_evaluation_result) ? $submission->ai_evaluation_result : [];
            $aiEvaluationStatus = (string) ($aiEvaluationResult['ai_evaluation_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($aiEvaluationStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_AI_CHECKED
                    : Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            ]);

            $submission = $submission->fresh();
        }

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATION) {
            $postEvaluationValidationResult = is_array($submission->post_evaluation_validation_result) ? $submission->post_evaluation_validation_result : [];
            $postEvaluationValidationStatus = (string) ($postEvaluationValidationResult['post_evaluation_validation_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($postEvaluationValidationStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED
                    : Submission::PIPELINE_STATUS_AI_CHECKED,
            ]);

            $submission = $submission->fresh();
        }

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_RESULT_PRESENTATION) {
            $resultPresentationResult = is_array($submission->result_presentation_result) ? $submission->result_presentation_result : [];
            $resultPresentationStatus = (string) ($resultPresentationResult['result_presentation_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($resultPresentationStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_EVALUATED
                    : Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            ]);

            $submission = $submission->fresh();
        }

        // Pastikan relasi terload agar Vue tidak membaca 'undefined'
        $submission->load([
            'user',
            'quest.taskBank:id,uuid,name,assessment_type',
            'quest.taskBank.questions' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->select([
                        'id',
                        'uuid',
                        'task_bank_id',
                        'question_text',
                        'question_type',
                        'options_json',
                        'answer_key',
                        'weight',
                        'sort_order',
                    ]);
            },
            'quest.rubric:id,title,max_score,mentor_id',
        ]);

        $rubric = $this->resolveRubricForSubmission($submission);
        $rubricPayload = $rubric ? $this->buildRubricEvaluationPayload($rubric) : null;
        $rubricSource = $rubric ? 'quest' : null;

        return Inertia::render('Quests/Admin/Inspect', [
            'submission' => $submission,
            'rubric' => $rubricPayload,
            'rubricSource' => $rubricSource,
            'aiAdvisorConfig' => [
                'auto_apply_min_confidence' => (int) config('services.ai.auto_apply_min_confidence', 65),
            ],
        ]);
    }

    public function previewFile(Submission $submission)
    {
        $this->assertMentorCanAccessSubmission($submission);

        $storedPath = (string) ($submission->file_path ?? '');
        abort_if($storedPath === '', 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($storedPath), 404);

        $absolutePath = $disk->path($storedPath);
        $mimeType = (string) ($disk->mimeType($storedPath) ?: 'application/octet-stream');
        $safeFilename = str_replace(['"', "\r", "\n"], '', basename($storedPath));

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
        ]);
    }

    /**
     * Memproses penilaian (Verdict) dan memberikan reward Gold/EXP.
     */
 public function verdict(Request $request, Submission $submission, RubricScoringService $scoring, LmsNotificationService $notifications, UserRewardSyncService $rewardSync)
{
    $this->assertMentorCanAccessSubmission($submission);

    $quest = $submission->quest;
    abort_if(! $quest, 404);
    $submission->loadMissing('user:id,role');
    $isStaffPlayModeTarget = (bool) $submission->user?->isStaffPlayMode();

    $newScore = 0;
    $verdictDetail = [];
    $validated = [];

    // --- TASK BANK GRADING (Question bank: rubric disabled) ---
    $quest->loadMissing('taskBank:id,assessment_type');
    if ($quest->taskBank) {
        $validated = $request->validate([
            'status' => ['required', 'in:Approved,Rejected'],
            'feedback' => ['nullable', 'string'],
            'question_points' => ['nullable', 'array'],
            'question_points.*' => ['nullable', 'numeric'],
        ]);

        $taskBank = $quest->taskBank;
        $questions = $taskBank->questions()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'uuid',
                'question_type',
                'answer_key',
                'weight',
            ]);

        $scoresDetail = $submission->scores_detail;
        $answers = is_array($scoresDetail) && isset($scoresDetail['answers']) && is_array($scoresDetail['answers'])
            ? $scoresDetail['answers']
            : [];

        $questionPoints = is_array($validated['question_points'] ?? null) ? $validated['question_points'] : [];
        $isAutoCheckedTaskBank = is_array($scoresDetail)
            && ($scoresDetail['source'] ?? null) === 'task_bank_auto_check'
            && $questions->isNotEmpty()
            && $questions->every(fn ($question) => in_array((string) ($question->question_type ?? ''), ['multiple_choice', 'platforming', 'word_match'], true));

        if ($isAutoCheckedTaskBank) {
            $newScore = max(0, min(100, (int) ($submission->grade ?? ($scoresDetail['grade'] ?? 0))));
            $verdictDetail = [
                'source' => 'task_bank_auto_check',
                'task_bank' => [
                    'assessment_type' => (string) ($taskBank->assessment_type ?? 'unknown'),
                    'percent' => $newScore,
                    'correct_questions' => (int) ($scoresDetail['correct_questions'] ?? 0),
                    'total_questions' => (int) ($scoresDetail['total_questions'] ?? 0),
                    'answers' => $answers,
                ],
            ];
        } else {

        $maxPoints = 0;
        $earnedPoints = 0.0;
        $mcqEarned = 0.0;
        $mcqMax = 0;
        $essayEarned = 0.0;
        $essayMax = 0;
        $weightedScoreTotal = 0.0;
        $mcqWeightedScore = 0.0;
        $essayWeightedScore = 0.0;
        $autoMcq = [];
        $manualEssay = [];
        $errors = [];

        foreach ($questions as $question) {
            $qUuid = (string) $question->uuid;
            $weight = max(0, (int) ($question->weight ?? 0));
            $maxPoints += $weight;

            $answer = trim((string) ($answers[$qUuid] ?? ''));
            $type = (string) ($question->question_type ?? 'essay');

            if ($type === 'multiple_choice') {
                $mcqMax += $weight;
                $answerKey = trim((string) ($question->answer_key ?? ''));
                $isCorrect = $answerKey !== '' && $answer !== '' && $answer === $answerKey;
                $scorePercent = $isCorrect ? 100.0 : 0.0;
                $points = round(($scorePercent / 100) * $weight, 2);
                $mcqEarned += $points;
                $earnedPoints += $points;
                $mcqWeightedScore += $scorePercent * $weight;
                $weightedScoreTotal += $scorePercent * $weight;

                $autoMcq[$qUuid] = [
                    'weight' => $weight,
                    'is_correct' => $isCorrect,
                    'score_percent' => (int) round($scorePercent),
                    'earned_points' => $points,
                ];

                continue;
            }

            $essayMax += $weight;
            $raw = $questionPoints[$qUuid] ?? null;

            if ($raw === null || $raw === '') {
                $errors["question_points.{$qUuid}"] = 'Skor essay wajib diisi.';
                continue;
            }

            $scorePercent = (float) $raw;
            if ($scorePercent < 0 || $scorePercent > 100) {
                $errors["question_points.{$qUuid}"] = 'Skor essay harus 0-100.';
                continue;
            }

            $points = round(($scorePercent / 100) * $weight, 2);
            $essayEarned += $points;
            $earnedPoints += $points;
            $essayWeightedScore += $scorePercent * $weight;
            $weightedScoreTotal += $scorePercent * $weight;
            $manualEssay[$qUuid] = [
                'weight' => $weight,
                'score_percent' => round($scorePercent, 2),
                'earned_points' => $points,
            ];
        }

        if (! empty($errors) && in_array((string) ($taskBank->assessment_type ?? ''), ['essay', 'mixed'], true)) {
            throw ValidationException::withMessages($errors);
        }

        $newScore = $maxPoints > 0 ? (int) round($weightedScoreTotal / $maxPoints) : 0;
        $newScore = max(0, min(100, $newScore));

        $mcqPercent = $mcqMax > 0 ? (int) round($mcqWeightedScore / $mcqMax) : null;
        $essayPercent = $essayMax > 0 ? (int) round($essayWeightedScore / $essayMax) : null;

        $verdictDetail = [
            'source' => (string) ($taskBank->assessment_type ?? 'task_bank'),
            'task_bank' => [
                'assessment_type' => (string) ($taskBank->assessment_type ?? 'unknown'),
                'max_points' => $maxPoints,
                'earned_points' => round($earnedPoints, 2),
                'percent' => $newScore,
                'mcq' => [
                    'max_points' => $mcqMax,
                    'earned_points' => round($mcqEarned, 2),
                    'percent' => $mcqPercent,
                    'by_question' => $autoMcq,
                ],
                'essay' => [
                    'max_points' => $essayMax,
                    'earned_points' => round($essayEarned, 2),
                    'percent' => $essayPercent,
                    'by_question' => $manualEssay,
                ],
            ],
        ];
        }
    } else {
        // --- MANUAL QUEST GRADING (Rubric optional) ---
        $rubric = $this->resolveRubricForSubmission($submission);

        if ($rubric) {
            $validated = $request->validate([
            'status' => ['required', 'in:Approved,Rejected'],
            'feedback' => ['nullable', 'string'],
            'selected_levels' => ['required', 'array', 'min:1'],
            'selected_levels.*' => ['nullable', 'integer'],
            ]);

            $rubric->loadMissing(['criteria:id,rubric_id,name,weight,order', 'levels:id,rubric_id,score_value']);
            $criteriaIds = $rubric->criteria->pluck('id')->map(fn ($v) => (int) $v)->all();
            $levelsById = $rubric->levels->keyBy('id');

            $selected = [];
            $errors = [];

            foreach (($validated['selected_levels'] ?? []) as $criteriaId => $levelId) {
                $criteriaId = (int) $criteriaId;
                $levelId = (int) ($levelId ?? 0);

                if ($criteriaId <= 0 || ! in_array($criteriaId, $criteriaIds, true)) {
                    continue;
                }

                if ($levelId <= 0 || ! $levelsById->has($levelId)) {
                    $errors["selected_levels.{$criteriaId}"] = 'Level wajib dipilih untuk setiap kriteria.';
                    continue;
                }

                $selected[$criteriaId] = $levelId;
            }

            foreach ($criteriaIds as $criteriaId) {
                if (! isset($selected[$criteriaId])) {
                    $errors["selected_levels.{$criteriaId}"] = 'Level wajib dipilih untuk setiap kriteria.';
                }
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            $result = $scoring->calculate($rubric, $selected);
            $maxRaw = (float) ($result['max_score'] ?? 0);
            $totalRaw = (float) ($result['total'] ?? 0);

            $newScore = $maxRaw > 0 ? (int) round(($totalRaw / $maxRaw) * 100) : 0;
            $newScore = max(0, min(100, $newScore));

            $verdictDetail = [
                'source' => 'rubric',
                'rubric_id' => (int) $rubric->id,
                'selected_level_by_criteria_id' => $selected,
                'result' => [
                    'total_raw' => $totalRaw,
                    'max_raw' => $maxRaw,
                    'percent' => $newScore,
                    'breakdown' => $result['breakdown'] ?? [],
                ],
            ];
        } else {
            $validated = $request->validate([
                'final_score' => ['required', 'numeric', 'min:0', 'max:100'],
                'feedback' => ['nullable', 'string'],
                'status' => ['required', 'in:Approved,Rejected'],
            ]);

            $newScore = (int) $validated['final_score'];
            $newScore = max(0, min(100, $newScore));

            $verdictDetail = [
                'source' => 'manual_score',
                'final_score' => $newScore,
            ];
        }
    }

    $newPortion = $newScore / 100;

    $questExp = (int) ($quest->reward_exp ?? 0);
    if ($questExp <= 0) {
        // Fallback untuk data quest lama yang belum punya reward_exp valid.
        $questExp = (int) ($quest->reward_gold ?? 0);
    }

    // Reward murni proporsional dari nilai akhir (tanpa nilai minimum).
    $finalGold = (int) floor($quest->reward_gold * $newPortion);
    $finalExp  = (int) floor($questExp * $newPortion);

    if ($isStaffPlayModeTarget) {
        $finalGold = 0;
        $finalExp = 0;
        $verdictDetail['staff_play_mode'] = [
            'active' => true,
            'reward_counted' => false,
        ];
    }
    if (! $submission->reward_eligible) {
        $finalGold = 0;
        $finalExp = 0;
        $verdictDetail['attempt_reward'] = [
            'eligible' => false,
            'reason' => 'Reward hanya diberikan pada attempt pertama.',
        ];
    }

    $scoresDetail = $submission->scores_detail;
    if (! is_array($scoresDetail)) {
        $scoresDetail = [];
    }
    $scoresDetail['verdict'] = $verdictDetail;

    $submission->update([
        'grade'    => $newScore,
        'feedback' => $validated['feedback'] ?? null,
        'status'   => $validated['status'],
        'earned_gold' => $finalGold,
        'earned_exp' => $finalExp,
        'scores_detail' => $scoresDetail,
    ]);

    $rewardSync->sync((int) $submission->user_id);
    $notifications->notifyGradeReleased($submission->fresh(['user', 'quest']));

    CacheVersion::bump('dashboard');

    return redirect()->back()->with('message', 'Verdict Processed & Rewards Calculated!');
}
    /**
     * Fitur AI Advisor (real provider + fallback).
     */
    public function previewAiPayload(Request $request, Submission $submission, SubmissionAiAdvisorService $advisorService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if (! in_array($submission->pipeline_status, [Submission::PIPELINE_STATUS_PREPROCESSED, Submission::PIPELINE_STATUS_CLEANED, Submission::PIPELINE_STATUS_STRUCTURED, Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED, Submission::PIPELINE_STATUS_RUBRIC_PREPARED, Submission::PIPELINE_STATUS_AI_EVALUATION, Submission::PIPELINE_STATUS_AI_CHECKED, Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATION, Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED, Submission::PIPELINE_STATUS_RESULT_PRESENTATION, Submission::PIPELINE_STATUS_EVALUATED], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI_PREPROCESS_NOT_DONE',
            ], 422);
        }

        $validated = $request->validate([
            'advisor_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $preview = $advisorService->previewPayload($submission, $validated['advisor_note'] ?? null);
        } catch (Throwable) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI_ADVISOR_PREVIEW_UNAVAILABLE',
            ], 503);
        }

        return response()->json([
            'status' => 'success',
            'preview' => $preview,
        ]);
    }

    public function checkWithAI(Request $request, Submission $submission, SubmissionAiAdvisorService $advisorService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if (! in_array($submission->pipeline_status, [Submission::PIPELINE_STATUS_PREPROCESSED, Submission::PIPELINE_STATUS_CLEANED, Submission::PIPELINE_STATUS_STRUCTURED, Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED, Submission::PIPELINE_STATUS_RUBRIC_PREPARED, Submission::PIPELINE_STATUS_AI_EVALUATION, Submission::PIPELINE_STATUS_AI_CHECKED, Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATION, Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED, Submission::PIPELINE_STATUS_RESULT_PRESENTATION, Submission::PIPELINE_STATUS_EVALUATED], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI_PREPROCESS_NOT_DONE',
            ], 422);
        }

        $submission->load('quest');

        $validated = $request->validate([
            'advisor_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $advisorService->analyze($submission, $validated['advisor_note'] ?? null);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI_ADVISOR_UNAVAILABLE',
            ], 503);
        }

        $advisor = $result['advisor'];
        $range = (string) ($advisor['suggested_score_range'] ?? '60-75');
        [$minScore, $maxScore] = $this->parseScoreRange($range);
        $midScore = (int) round(($minScore + $maxScore) / 2);

        $feedbackSummary = trim((string) ($advisor['summary'] ?? ''));
        $feedbackSuggestion = trim((string) ($advisor['suggested_feedback'] ?? ''));
        $feedbackPayload = trim($feedbackSummary.' '.$feedbackSuggestion);

        $scoresDetail = $submission->scores_detail;
        if (! is_array($scoresDetail)) {
            $scoresDetail = [];
        }
        $scoresDetail['ai_advisor'] = [
            ...$advisor,
            'provider_used' => $result['provider_used'],
            'is_fallback' => $result['is_fallback'],
            'latency_ms' => $result['latency_ms'],
            'artifact_source_flags' => $result['artifact_source_flags'] ?? [],
            'artifact_warnings' => $result['artifact_warnings'] ?? [],
            'used_local_preprocessor' => (bool) ($result['used_local_preprocessor'] ?? false),
            'preprocessed_key_points' => $result['preprocessed_key_points'] ?? [],
            'task_bank_context_present' => (bool) ($result['task_bank_context_present'] ?? false),
            'rubric_context_present' => (bool) ($result['rubric_context_present'] ?? false),
            'evidence_quality_score' => (float) ($result['evidence_quality_score'] ?? 0),
            'evidence_quality_warnings' => $result['evidence_quality_warnings'] ?? [],
            'confidence' => $advisor['confidence'] ?? ['overall' => 0, 'rubric' => 0, 'task_bank' => 0, 'notes' => ''],
            'generated_at' => now()->toIso8601String(),
            'merged_qa_stats' => $result['merged_qa_stats'] ?? null,
        ];

        $submission->update([
            'scores_detail' => $scoresDetail,
        ]);

        return response()->json([
            'status' => 'success',
            'summary' => $advisor['summary'] ?? '',
            'strengths' => $advisor['strengths'] ?? [],
            'gaps' => $advisor['gaps'] ?? [],
            'risk_flags' => $advisor['risk_flags'] ?? [],
            'suggested_score_range' => $range,
            'suggested_feedback' => $advisor['suggested_feedback'] ?? '',
            'rubric_recommendations' => $advisor['rubric_recommendations'] ?? [],
            'task_bank_findings' => $advisor['task_bank_findings'] ?? [],
            'essay_scores' => $advisor['essay_scores'] ?? [],
            'question_feedback' => $advisor['question_feedback'] ?? [],
            'score_calibration' => $advisor['score_calibration'] ?? null,
            'provider_used' => $result['provider_used'],
            'is_fallback' => $result['is_fallback'],
            'latency_ms' => $result['latency_ms'],
            'artifact_source_flags' => $result['artifact_source_flags'] ?? [],
            'artifact_warnings' => $result['artifact_warnings'] ?? [],
            'used_local_preprocessor' => (bool) ($result['used_local_preprocessor'] ?? false),
            'preprocessed_key_points' => $result['preprocessed_key_points'] ?? [],
            'task_bank_context_present' => (bool) ($result['task_bank_context_present'] ?? false),
            'rubric_context_present' => (bool) ($result['rubric_context_present'] ?? false),
            'evidence_quality_score' => (float) ($result['evidence_quality_score'] ?? 0),
            'evidence_quality_warnings' => $result['evidence_quality_warnings'] ?? [],
            'confidence' => $advisor['confidence'] ?? ['overall' => 0, 'rubric' => 0, 'task_bank' => 0, 'notes' => ''],
            'merged_qa_stats' => $result['merged_qa_stats'] ?? null,

            // Legacy compatibility for current UI consumer.
            'scores' => [
                'func' => $midScore,
                'logic' => $midScore,
                'neat' => $midScore,
                'extra' => 0,
                'att' => 0,
            ],
            'func' => $midScore,
            'logic' => $midScore,
            'clean' => $midScore,
            'feedback' => $feedbackPayload,
        ]);
    }

    /**
     * @return array{0:int,1:int}
     */
    public function startPreprocessing(Request $request, Submission $submission, RawSubmissionExtractionService $extractionService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if (! in_array($submission->pipeline_status, [
            Submission::PIPELINE_STATUS_PENDING_PREPROCESSING,
            Submission::PIPELINE_STATUS_PREPROCESSED,
            Submission::PIPELINE_STATUS_CLEANED,
            Submission::PIPELINE_STATUS_STRUCTURED,
            Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
            Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            Submission::PIPELINE_STATUS_AI_CHECKED,
            Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
            Submission::PIPELINE_STATUS_EVALUATED,
        ], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SUBMISSION_NOT_READY_FOR_PREPROCESSING',
                ], 422);
            }

            return back()->with('error', 'SUBMISSION_NOT_READY_FOR_PREPROCESSING');
        }

        $submission->update([
            'pipeline_status' => Submission::PIPELINE_STATUS_PREPROCESSING,
            'preprocess_started' => true,
        ]);

        try {
            $result = $extractionService->extract($submission->fresh());
        } catch (Throwable) {
            $result = [
                'submission_id' => (string) ($submission->submission_id ?: $submission->uuid),
                'detected_content_type' => (string) ($submission->file_type ?: 'txt'),
                'extraction_method' => 'txt_reader',
                'raw_text' => '',
                'page_count' => 0,
                'ocr_used' => false,
                'ocr_confidence' => null,
                'extraction_status' => 'failed',
                'warnings' => ['extraction_exception'],
            ];
        }

        $extractionSucceeded = (string) ($result['extraction_status'] ?? 'failed') === 'success';

        $submission->update([
            'pipeline_status' => $extractionSucceeded
                ? Submission::PIPELINE_STATUS_PREPROCESSED
                : Submission::PIPELINE_STATUS_PENDING_PREPROCESSING,
            'preprocess_started' => $extractionSucceeded,
            'extracted_text' => (string) ($result['raw_text'] ?? ''),
            'extraction_result' => $result,
            'extracted_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($result, $extractionSucceeded ? 200 : 422);
        }

        return back()->with($extractionSucceeded ? 'message' : 'error', $extractionSucceeded ? 'EXTRACTION_COMPLETED' : 'EXTRACTION_FAILED');
    }

    public function startCleaning(Request $request, Submission $submission, CleanSubmissionTextService $cleaningService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if (! in_array($submission->pipeline_status, [
            Submission::PIPELINE_STATUS_PREPROCESSED,
            Submission::PIPELINE_STATUS_CLEANED,
            Submission::PIPELINE_STATUS_STRUCTURED,
            Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
            Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            Submission::PIPELINE_STATUS_AI_CHECKED,
            Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
            Submission::PIPELINE_STATUS_EVALUATED,
        ], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SUBMISSION_NOT_READY_FOR_CLEANING',
                ], 422);
            }

            return back()->with('error', 'SUBMISSION_NOT_READY_FOR_CLEANING');
        }

        $extractionResult = is_array($submission->extraction_result) ? $submission->extraction_result : [];
        if ((string) ($extractionResult['extraction_status'] ?? '') !== 'success' || trim((string) ($submission->extracted_text ?? '')) === '') {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'EXTRACTION_RESULT_REQUIRED',
                ], 422);
            }

            return back()->with('error', 'EXTRACTION_RESULT_REQUIRED');
        }

        $submission->update([
            'pipeline_status' => Submission::PIPELINE_STATUS_CLEANING,
        ]);

        try {
            $result = $cleaningService->clean($submission->fresh());
        } catch (Throwable) {
            $result = [
                'submission_id' => (string) ($submission->submission_id ?: $submission->uuid),
                'clean_text' => '',
                'language' => 'unknown',
                'cleaning_status' => 'failed',
                'changes_summary' => [
                    'noise_removed' => 0,
                    'ocr_corrections' => 0,
                    'line_break_fixed' => 0,
                    'garbage_removed' => 0,
                ],
                'warnings' => ['cleaning_exception'],
                'next_stage' => 'structure_detection',
            ];
        }

        $cleaningSucceeded = in_array((string) ($result['cleaning_status'] ?? 'failed'), ['success', 'partial'], true);

        $submission->update([
            'pipeline_status' => $cleaningSucceeded
                ? Submission::PIPELINE_STATUS_CLEANED
                : Submission::PIPELINE_STATUS_PREPROCESSED,
            'clean_text' => (string) ($result['clean_text'] ?? ''),
            'cleaning_result' => $result,
            'cleaning_language' => (string) ($result['language'] ?? 'unknown'),
            'cleaned_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($result, $cleaningSucceeded ? 200 : 422);
        }

        return back()->with($cleaningSucceeded ? 'message' : 'error', $cleaningSucceeded ? 'CLEANING_COMPLETED' : 'CLEANING_FAILED');
    }

    public function startStructureDetection(Request $request, Submission $submission, DetectSubmissionStructureService $structureService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_STRUCTURE_DETECTION) {
            $existingResult = is_array($submission->structure_result) ? $submission->structure_result : [];
            $existingStatus = (string) ($existingResult['structure_detection_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($existingStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_STRUCTURED
                    : Submission::PIPELINE_STATUS_CLEANED,
            ]);

            $submission = $submission->fresh();
        }

        if (! in_array($submission->pipeline_status, [
            Submission::PIPELINE_STATUS_CLEANED,
            Submission::PIPELINE_STATUS_STRUCTURED,
            Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
            Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            Submission::PIPELINE_STATUS_AI_CHECKED,
            Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
            Submission::PIPELINE_STATUS_EVALUATED,
        ], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SUBMISSION_NOT_READY_FOR_STRUCTURE_DETECTION',
                ], 422);
            }

            return back()->with('error', 'SUBMISSION_NOT_READY_FOR_STRUCTURE_DETECTION');
        }

        $cleaningResult = is_array($submission->cleaning_result) ? $submission->cleaning_result : [];
        $cleaningStatus = (string) ($cleaningResult['cleaning_status'] ?? '');
        if (! in_array($cleaningStatus, ['success', 'partial'], true) || trim((string) ($submission->clean_text ?? '')) === '') {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CLEANING_RESULT_REQUIRED',
                ], 422);
            }

            return back()->with('error', 'CLEANING_RESULT_REQUIRED');
        }

        $submission->update([
            'pipeline_status' => Submission::PIPELINE_STATUS_STRUCTURE_DETECTION,
        ]);

        $failedResult = [
            'submission_id' => (string) ($submission->submission_id ?: $submission->uuid),
            'document_pattern' => 'mixed',
            'items' => [],
            'instruction_blocks' => [],
            'warnings' => ['structure_detection_exception'],
            'structure_detection_status' => 'failed',
            'next_stage' => 'semantic_enrichment',
        ];

        $result = $failedResult;
        $structureSucceeded = false;

        try {
            $result = $structureService->detect($submission->fresh());
            $structureSucceeded = in_array((string) ($result['structure_detection_status'] ?? 'failed'), ['success', 'partial'], true);

            $submission->update([
                'pipeline_status' => $structureSucceeded
                    ? Submission::PIPELINE_STATUS_STRUCTURED
                    : Submission::PIPELINE_STATUS_CLEANED,
                'structured_items' => is_array($result['items'] ?? null) ? $result['items'] : [],
                'structure_result' => $result,
                'structure_detected_at' => now(),
            ]);
        } catch (Throwable) {
            try {
                Submission::query()
                    ->whereKey($submission->getKey())
                    ->where('pipeline_status', Submission::PIPELINE_STATUS_STRUCTURE_DETECTION)
                    ->update([
                        'pipeline_status' => Submission::PIPELINE_STATUS_CLEANED,
                    ]);
            } catch (Throwable) {
            }

            $result = $failedResult;
            $structureSucceeded = false;
        }

        if ($request->expectsJson()) {
            return response()->json($result, $structureSucceeded ? 200 : 422);
        }

        return back()->with($structureSucceeded ? 'message' : 'error', $structureSucceeded ? 'STRUCTURE_DETECTION_COMPLETED' : 'STRUCTURE_DETECTION_FAILED');
    }

    public function startSemanticEnrichment(Request $request, Submission $submission, EnrichSubmissionSemanticsService $semanticService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if (! in_array($submission->pipeline_status, [
            Submission::PIPELINE_STATUS_STRUCTURED,
            Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
            Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            Submission::PIPELINE_STATUS_AI_CHECKED,
            Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
            Submission::PIPELINE_STATUS_EVALUATED,
        ], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SUBMISSION_NOT_READY_FOR_SEMANTIC_ENRICHMENT',
                ], 422);
            }

            return back()->with('error', 'SUBMISSION_NOT_READY_FOR_SEMANTIC_ENRICHMENT');
        }

        $structureResult = is_array($submission->structure_result) ? $submission->structure_result : [];
        $structureStatus = (string) ($structureResult['structure_detection_status'] ?? '');
        $structuredItems = is_array($submission->structured_items) ? $submission->structured_items : [];
        if (! in_array($structureStatus, ['success', 'partial'], true) || $structuredItems === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'STRUCTURE_RESULT_REQUIRED',
                ], 422);
            }

            return back()->with('error', 'STRUCTURE_RESULT_REQUIRED');
        }

        $submission->update([
            'pipeline_status' => Submission::PIPELINE_STATUS_SEMANTIC_ENRICHMENT,
        ]);

        try {
            $result = $semanticService->enrich($submission->fresh());
        } catch (Throwable) {
            $result = [
                'submission_id' => (string) ($submission->submission_id ?: $submission->uuid),
                'items' => [],
                'warnings' => ['semantic_enrichment_exception'],
                'semantic_enrichment_status' => 'failed',
                'next_stage' => 'rubric_preparation',
            ];
        }

        $semanticSucceeded = in_array((string) ($result['semantic_enrichment_status'] ?? 'failed'), ['success', 'partial'], true);

        $submission->update([
            'pipeline_status' => $semanticSucceeded
                ? Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED
                : Submission::PIPELINE_STATUS_STRUCTURED,
            'semantic_items' => is_array($result['items'] ?? null) ? $result['items'] : [],
            'semantic_result' => $result,
            'semantic_enriched_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($result, $semanticSucceeded ? 200 : 422);
        }

        return back()->with($semanticSucceeded ? 'message' : 'error', $semanticSucceeded ? 'SEMANTIC_ENRICHMENT_COMPLETED' : 'SEMANTIC_ENRICHMENT_FAILED');
    }

    public function startRubricPreparation(Request $request, Submission $submission, PrepareSubmissionRubricService $rubricService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_RUBRIC_PREPARATION) {
            $existingResult = is_array($submission->rubric_preparation_result) ? $submission->rubric_preparation_result : [];
            $existingStatus = (string) ($existingResult['rubric_preparation_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($existingStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_RUBRIC_PREPARED
                    : Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
            ]);

            $submission = $submission->fresh();
        }

        if (! in_array($submission->pipeline_status, [
            Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
            Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            Submission::PIPELINE_STATUS_AI_CHECKED,
            Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
            Submission::PIPELINE_STATUS_EVALUATED,
        ], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SUBMISSION_NOT_READY_FOR_RUBRIC_PREPARATION',
                ], 422);
            }

            return back()->with('error', 'SUBMISSION_NOT_READY_FOR_RUBRIC_PREPARATION');
        }

        $semanticResult = is_array($submission->semantic_result) ? $submission->semantic_result : [];
        $semanticStatus = (string) ($semanticResult['semantic_enrichment_status'] ?? '');
        $semanticItems = is_array($submission->semantic_items) ? $submission->semantic_items : [];
        if (! in_array($semanticStatus, ['success', 'partial'], true) || $semanticItems === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SEMANTIC_RESULT_REQUIRED',
                ], 422);
            }

            return back()->with('error', 'SEMANTIC_RESULT_REQUIRED');
        }

        $submission->update([
            'pipeline_status' => Submission::PIPELINE_STATUS_RUBRIC_PREPARATION,
        ]);

        try {
            $result = $rubricService->prepare($submission->fresh());
        } catch (Throwable) {
            $result = [
                'submission_id' => (string) ($submission->submission_id ?: $submission->uuid),
                'items' => [],
                'warnings' => ['rubric_preparation_exception'],
                'rubric_preparation_status' => 'failed',
                'next_stage' => 'ai_evaluation',
            ];
        }

        $rubricPreparationSucceeded = in_array((string) ($result['rubric_preparation_status'] ?? 'failed'), ['success', 'partial'], true);

        $submission->update([
            'pipeline_status' => $rubricPreparationSucceeded
                ? Submission::PIPELINE_STATUS_RUBRIC_PREPARED
                : Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
            'rubric_preparation_items' => is_array($result['items'] ?? null) ? $result['items'] : [],
            'rubric_preparation_result' => $result,
            'rubric_prepared_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($result, $rubricPreparationSucceeded ? 200 : 422);
        }

        return back()->with($rubricPreparationSucceeded ? 'message' : 'error', $rubricPreparationSucceeded ? 'RUBRIC_PREPARATION_COMPLETED' : 'RUBRIC_PREPARATION_FAILED');
    }

    public function startAiEvaluation(Request $request, Submission $submission, EvaluateSubmissionAnswersService $evaluationService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_AI_EVALUATION) {
            $existingResult = is_array($submission->ai_evaluation_result) ? $submission->ai_evaluation_result : [];
            $existingStatus = (string) ($existingResult['ai_evaluation_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($existingStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_AI_CHECKED
                    : Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            ]);

            $submission = $submission->fresh();
        }

        if (! in_array($submission->pipeline_status, [
            Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            Submission::PIPELINE_STATUS_AI_CHECKED,
            Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
            Submission::PIPELINE_STATUS_EVALUATED,
        ], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SUBMISSION_NOT_READY_FOR_AI_EVALUATION',
                ], 422);
            }

            return back()->with('error', 'SUBMISSION_NOT_READY_FOR_AI_EVALUATION');
        }

        $rubricPreparationResult = is_array($submission->rubric_preparation_result) ? $submission->rubric_preparation_result : [];
        $rubricPreparationStatus = (string) ($rubricPreparationResult['rubric_preparation_status'] ?? '');
        $rubricPreparationItems = is_array($submission->rubric_preparation_items) ? $submission->rubric_preparation_items : [];
        if (! in_array($rubricPreparationStatus, ['success', 'partial'], true) || $rubricPreparationItems === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'RUBRIC_PREPARATION_RESULT_REQUIRED',
                ], 422);
            }

            return back()->with('error', 'RUBRIC_PREPARATION_RESULT_REQUIRED');
        }

        $submission->update([
            'pipeline_status' => Submission::PIPELINE_STATUS_AI_EVALUATION,
        ]);

        try {
            $result = $evaluationService->evaluate($submission->fresh());
        } catch (Throwable) {
            $result = [
                'submission_id' => (string) ($submission->submission_id ?: $submission->uuid),
                'items' => [],
                'warnings' => ['ai_evaluation_exception'],
                'ai_evaluation_status' => 'failed',
                'next_stage' => 'evaluation_quality_review',
            ];
        }

        $evaluationSucceeded = in_array((string) ($result['ai_evaluation_status'] ?? 'failed'), ['success', 'partial'], true);

        $submission->update([
            'pipeline_status' => $evaluationSucceeded
                ? Submission::PIPELINE_STATUS_AI_CHECKED
                : Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            'ai_evaluation_items' => is_array($result['items'] ?? null) ? $result['items'] : [],
            'ai_evaluation_result' => $result,
            'ai_evaluated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($result, $evaluationSucceeded ? 200 : 422);
        }

        return back()->with($evaluationSucceeded ? 'message' : 'error', $evaluationSucceeded ? 'AI_EVALUATION_COMPLETED' : 'AI_EVALUATION_FAILED');
    }

    public function rerunAiEvaluation(Request $request, Submission $submission, EvaluateSubmissionAnswersService $evaluationService, ValidatePostEvaluationResultService $validationService, PresentSubmissionResultService $presentationService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if (! in_array($submission->pipeline_status, [
            Submission::PIPELINE_STATUS_AI_CHECKED,
            Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
            Submission::PIPELINE_STATUS_EVALUATED,
        ], true)) {
            return response()->json(['status' => 'error', 'message' => 'SUBMISSION_NOT_ELIGIBLE_FOR_RERUN'], 422);
        }

        $rubricPreparationItems = is_array($submission->rubric_preparation_items) ? $submission->rubric_preparation_items : [];
        if ($rubricPreparationItems === []) {
            return response()->json(['status' => 'error', 'message' => 'RUBRIC_PREPARATION_RESULT_REQUIRED'], 422);
        }

        // Stage 6: AI Evaluation
        $submission->update(['pipeline_status' => Submission::PIPELINE_STATUS_AI_EVALUATION]);
        try {
            $evalResult = $evaluationService->evaluate($submission->fresh());
        } catch (Throwable) {
            $evalResult = ['submission_id' => (string) ($submission->submission_id ?: $submission->uuid), 'items' => [], 'warnings' => ['ai_evaluation_exception'], 'ai_evaluation_status' => 'failed', 'next_stage' => 'evaluation_quality_review'];
        }

        $evalSucceeded = in_array((string) ($evalResult['ai_evaluation_status'] ?? 'failed'), ['success', 'partial'], true);
        $submission->update([
            'pipeline_status' => $evalSucceeded ? Submission::PIPELINE_STATUS_AI_CHECKED : Submission::PIPELINE_STATUS_RUBRIC_PREPARED,
            'ai_evaluation_items' => is_array($evalResult['items'] ?? null) ? $evalResult['items'] : [],
            'ai_evaluation_result' => $evalResult,
            'ai_evaluated_at' => now(),
        ]);

        if (! $evalSucceeded) {
            return response()->json(['status' => 'error', 'message' => 'AI_EVALUATION_FAILED', 'stage' => 'ai_evaluation', 'result' => $evalResult], 422);
        }

        // Stage 7: Post-Evaluation Validation
        $submission->update(['pipeline_status' => Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATION]);
        try {
            $postEvalResult = $validationService->validate($submission->fresh());
        } catch (Throwable) {
            $postEvalResult = ['submission_id' => (string) ($submission->submission_id ?: $submission->uuid), 'items' => [], 'warnings' => ['post_evaluation_validation_exception'], 'post_evaluation_validation_status' => 'failed', 'next_stage' => 'result_finalization'];
        }

        $postEvalSucceeded = in_array((string) ($postEvalResult['post_evaluation_validation_status'] ?? 'failed'), ['success', 'partial'], true);
        $submission->update([
            'pipeline_status' => $postEvalSucceeded ? Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED : Submission::PIPELINE_STATUS_AI_CHECKED,
            'post_evaluation_validation_items' => is_array($postEvalResult['items'] ?? null) ? $postEvalResult['items'] : [],
            'post_evaluation_validation_result' => $postEvalResult,
            'post_evaluation_validated_at' => now(),
        ]);

        if (! $postEvalSucceeded) {
            return response()->json(['status' => 'error', 'message' => 'POST_EVALUATION_VALIDATION_FAILED', 'stage' => 'post_evaluation_validation', 'result' => $postEvalResult], 422);
        }

        // Stage 8: Result Presentation
        $submission->update(['pipeline_status' => Submission::PIPELINE_STATUS_RESULT_PRESENTATION]);
        try {
            $presResult = $presentationService->present($submission->fresh());
        } catch (Throwable) {
            $presResult = ['submission_id' => (string) ($submission->submission_id ?: $submission->uuid), 'items' => [], 'warnings' => ['result_presentation_exception'], 'result_presentation_status' => 'failed', 'next_stage' => 'mentor_verdict'];
        }

        $presSucceeded = in_array((string) ($presResult['result_presentation_status'] ?? 'failed'), ['success', 'partial'], true);
        $submission->update([
            'pipeline_status' => $presSucceeded ? Submission::PIPELINE_STATUS_EVALUATED : Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            'result_presentation_items' => is_array($presResult['items'] ?? null) ? $presResult['items'] : [],
            'result_presentation_result' => $presResult,
            'result_presented_at' => now(),
        ]);

        return response()->json([
            'status' => $presSucceeded ? 'success' : 'partial',
            'message' => $presSucceeded ? 'RERUN_AI_EVALUATION_COMPLETED' : 'RERUN_PARTIAL_FAILURE',
            'stages' => [
                'ai_evaluation' => (string) ($evalResult['ai_evaluation_status'] ?? 'failed'),
                'post_evaluation_validation' => (string) ($postEvalResult['post_evaluation_validation_status'] ?? 'failed'),
                'result_presentation' => (string) ($presResult['result_presentation_status'] ?? 'failed'),
            ],
        ], $presSucceeded ? 200 : 422);
    }

    public function startPostEvaluationValidation(Request $request, Submission $submission, ValidatePostEvaluationResultService $validationService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATION) {
            $existingResult = is_array($submission->post_evaluation_validation_result) ? $submission->post_evaluation_validation_result : [];
            $existingStatus = (string) ($existingResult['post_evaluation_validation_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($existingStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED
                    : Submission::PIPELINE_STATUS_AI_CHECKED,
            ]);

            $submission = $submission->fresh();
        }

        if (! in_array($submission->pipeline_status, [
            Submission::PIPELINE_STATUS_AI_CHECKED,
            Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
            Submission::PIPELINE_STATUS_EVALUATED,
        ], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SUBMISSION_NOT_READY_FOR_POST_EVALUATION_VALIDATION',
                ], 422);
            }

            return back()->with('error', 'SUBMISSION_NOT_READY_FOR_POST_EVALUATION_VALIDATION');
        }

        $aiEvaluationResult = is_array($submission->ai_evaluation_result) ? $submission->ai_evaluation_result : [];
        $aiEvaluationStatus = (string) ($aiEvaluationResult['ai_evaluation_status'] ?? '');
        $aiEvaluationItems = is_array($submission->ai_evaluation_items) ? $submission->ai_evaluation_items : [];

        if (! in_array($aiEvaluationStatus, ['success', 'partial'], true) || $aiEvaluationItems === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'AI_EVALUATION_RESULT_REQUIRED',
                ], 422);
            }

            return back()->with('error', 'AI_EVALUATION_RESULT_REQUIRED');
        }

        $submission->update([
            'pipeline_status' => Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATION,
        ]);

        try {
            $result = $validationService->validate($submission->fresh());
        } catch (Throwable) {
            $result = [
                'submission_id' => (string) ($submission->submission_id ?: $submission->uuid),
                'items' => [],
                'warnings' => ['post_evaluation_validation_exception'],
                'post_evaluation_validation_status' => 'failed',
                'next_stage' => 'result_finalization',
            ];
        }

        $validationSucceeded = in_array((string) ($result['post_evaluation_validation_status'] ?? 'failed'), ['success', 'partial'], true);

        $submission->update([
            'pipeline_status' => $validationSucceeded
                ? Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED
                : Submission::PIPELINE_STATUS_AI_CHECKED,
            'post_evaluation_validation_items' => is_array($result['items'] ?? null) ? $result['items'] : [],
            'post_evaluation_validation_result' => $result,
            'post_evaluation_validated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($result, $validationSucceeded ? 200 : 422);
        }

        return back()->with($validationSucceeded ? 'message' : 'error', $validationSucceeded ? 'POST_EVALUATION_VALIDATION_COMPLETED' : 'POST_EVALUATION_VALIDATION_FAILED');
    }

    public function startResultPresentation(Request $request, Submission $submission, PresentSubmissionResultService $presentationService)
    {
        $this->assertMentorCanAccessSubmission($submission);

        if ($submission->pipeline_status === Submission::PIPELINE_STATUS_RESULT_PRESENTATION) {
            $existingResult = is_array($submission->result_presentation_result) ? $submission->result_presentation_result : [];
            $existingStatus = (string) ($existingResult['result_presentation_status'] ?? 'failed');

            $submission->update([
                'pipeline_status' => in_array($existingStatus, ['success', 'partial'], true)
                    ? Submission::PIPELINE_STATUS_EVALUATED
                    : Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            ]);

            $submission = $submission->fresh();
        }

        if (! in_array($submission->pipeline_status, [Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED, Submission::PIPELINE_STATUS_EVALUATED], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SUBMISSION_NOT_READY_FOR_RESULT_PRESENTATION',
                ], 422);
            }

            return back()->with('error', 'SUBMISSION_NOT_READY_FOR_RESULT_PRESENTATION');
        }

        $postEvaluationValidationResult = is_array($submission->post_evaluation_validation_result) ? $submission->post_evaluation_validation_result : [];
        $postEvaluationValidationStatus = (string) ($postEvaluationValidationResult['post_evaluation_validation_status'] ?? '');
        $postEvaluationValidationItems = is_array($submission->post_evaluation_validation_items) ? $submission->post_evaluation_validation_items : [];
        if ($postEvaluationValidationItems === [] && is_array($postEvaluationValidationResult['items'] ?? null)) {
            $postEvaluationValidationItems = $postEvaluationValidationResult['items'];
        }

        if (! in_array($postEvaluationValidationStatus, ['success', 'partial'], true) || $postEvaluationValidationItems === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'POST_EVALUATION_VALIDATION_RESULT_REQUIRED',
                ], 422);
            }

            return back()->with('error', 'POST_EVALUATION_VALIDATION_RESULT_REQUIRED');
        }

        $submission->update([
            'pipeline_status' => Submission::PIPELINE_STATUS_RESULT_PRESENTATION,
        ]);

        try {
            $result = $presentationService->present($submission->fresh());
        } catch (Throwable) {
            $result = [
                'submission_id' => (string) ($submission->submission_id ?: $submission->uuid),
                'items' => [],
                'warnings' => ['result_presentation_exception'],
                'result_presentation_status' => 'failed',
                'next_stage' => 'mentor_verdict',
            ];
        }

        $presentationSucceeded = in_array((string) ($result['result_presentation_status'] ?? 'failed'), ['success', 'partial'], true);

        $submission->update([
            'pipeline_status' => $presentationSucceeded
                ? Submission::PIPELINE_STATUS_EVALUATED
                : Submission::PIPELINE_STATUS_POST_EVALUATION_VALIDATED,
            'result_presentation_items' => is_array($result['items'] ?? null) ? $result['items'] : [],
            'result_presentation_result' => $result,
            'result_presented_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($result, $presentationSucceeded ? 200 : 422);
        }

        return back()->with($presentationSucceeded ? 'message' : 'error', $presentationSucceeded ? 'RESULT_PRESENTATION_COMPLETED' : 'RESULT_PRESENTATION_FAILED');
    }

    private function parseScoreRange(string $range): array
    {
        if (! preg_match('/^(\d{1,3})\s*-\s*(\d{1,3})$/', trim($range), $matches)) {
            return [60, 75];
        }

        $minScore = max(1, min(100, (int) $matches[1]));
        $maxScore = max(1, min(100, (int) $matches[2]));

        if ($minScore > $maxScore) {
            [$minScore, $maxScore] = [$maxScore, $minScore];
        }

        return [$minScore, $maxScore];
    }

    private function isMentorUser(): bool
    {
        return (bool) auth()->user()?->isMentor();
    }

    private function requireMentorJobId(): int
    {
        $jobId = (int) (auth()->user()?->job_id ?? 0);
        abort_if($jobId <= 0, 403, self::MENTOR_JOB_REQUIRED_MESSAGE);
        return $jobId;
    }

    private function assertMentorCanAccessSubmission(Submission $submission): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();
        $submission->loadMissing([
            'quest.studyGroup:id,job_id',
            'quest.taskBank:id,job_role_id',
        ]);

        $quest = $submission->quest;
        abort_if(! $quest, 403, 'MENTOR_CANNOT_ACCESS_SUBMISSION_WITHOUT_QUEST');

        $studyGroupJobId = (int) ($quest->studyGroup?->job_id ?? 0);
        $taskBankJobId = (int) ($quest->taskBank?->job_role_id ?? 0);
        $isAllowed = $studyGroupJobId === $mentorJobId || $taskBankJobId === $mentorJobId;

        abort_unless($isAllowed, 403, 'MENTOR_CANNOT_ACCESS_SUBMISSION_OUTSIDE_JOB');
    }

    private function resolveRubricForSubmission(Submission $submission): ?Rubric
    {
        $submission->loadMissing([
            'quest.rubric',
            'quest.taskBank',
        ]);

        $quest = $submission->quest;
        if (! $quest) {
            return null;
        }

        // Rubric hanya untuk manual quest (tanpa question bank).
        if ($quest->taskBank) {
            return null;
        }

        return $quest->rubric;
    }

    private function buildRubricEvaluationPayload(Rubric $rubric): array
    {
        $rubric->loadMissing([
            'criteria',
            'levels',
        ]);

        $criteriaIds = $rubric->criteria->pluck('id')->map(fn ($v) => (int) $v)->all();
        $descriptions = count($criteriaIds)
            ? RubricDescription::query()->whereIn('criteria_id', $criteriaIds)->get()
            : collect();

        $matrix = [];
        foreach ($descriptions as $desc) {
            $matrix[(int) $desc->criteria_id][(int) $desc->level_id] = $desc->description;
        }

        return [
            'rubric' => [
                'id' => $rubric->id,
                'title' => $rubric->title,
                'max_score' => (float) $rubric->max_score,
            ],
            'criteria' => $rubric->criteria->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'weight' => (float) $c->weight,
                'order' => $c->order,
            ])->values()->all(),
            'levels' => $rubric->levels->map(fn ($l) => [
                'id' => $l->id,
                'level' => $l->level,
                'label' => $l->label,
                'score_value' => (float) $l->score_value,
            ])->values()->all(),
            'matrix' => $matrix,
        ];
    }
}
