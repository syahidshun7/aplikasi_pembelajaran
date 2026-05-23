<?php

namespace App\Http\Controllers;

use App\Models\Rubric;
use App\Models\RubricDescription;
use App\Models\Submission;
use App\Models\User;
use App\Services\Ai\SubmissionAiAdvisorService;
use App\Services\LmsNotificationService;
use App\Services\RubricScoringService;
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

        $maxPoints = 0;
        $earnedPoints = 0;
        $mcqEarned = 0;
        $mcqMax = 0;
        $essayEarned = 0;
        $essayMax = 0;
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
                $points = $isCorrect ? $weight : 0;
                $mcqEarned += $points;
                $earnedPoints += $points;

                $autoMcq[$qUuid] = [
                    'weight' => $weight,
                    'is_correct' => $isCorrect,
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

            $points = (float) $raw;
            if ($points < 0 || $points > $weight) {
                $errors["question_points.{$qUuid}"] = "Skor essay harus 0–{$weight}.";
                continue;
            }

            $essayEarned += $points;
            $earnedPoints += $points;
            $manualEssay[$qUuid] = [
                'weight' => $weight,
                'earned_points' => $points,
            ];
        }

        if (! empty($errors) && in_array((string) ($taskBank->assessment_type ?? ''), ['essay', 'mixed'], true)) {
            throw ValidationException::withMessages($errors);
        }

        $newScore = $maxPoints > 0 ? (int) round(($earnedPoints / $maxPoints) * 100) : 0;
        $newScore = max(0, min(100, $newScore));

        $verdictDetail = [
            'source' => (string) ($taskBank->assessment_type ?? 'task_bank'),
            'task_bank' => [
                'assessment_type' => (string) ($taskBank->assessment_type ?? 'unknown'),
                'max_points' => $maxPoints,
                'earned_points' => $earnedPoints,
                'percent' => $newScore,
                'mcq' => [
                    'max_points' => $mcqMax,
                    'earned_points' => $mcqEarned,
                    'by_question' => $autoMcq,
                ],
                'essay' => [
                    'max_points' => $essayMax,
                    'earned_points' => $essayEarned,
                    'by_question' => $manualEssay,
                ],
            ],
        ];
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
                'final_score' => ['required', 'numeric', 'min:1', 'max:100'],
                'feedback' => ['nullable', 'string'],
                'status' => ['required', 'in:Approved,Rejected'],
            ]);

            $newScore = (int) $validated['final_score'];
            $newScore = max(1, min(100, $newScore));

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
