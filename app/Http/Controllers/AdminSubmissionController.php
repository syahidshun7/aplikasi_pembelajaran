<?php

namespace App\Http\Controllers;

use App\Models\Rubric;
use App\Models\RubricDescription;
use App\Models\Submission;
use App\Models\User;
use App\Support\Cache\CacheVersion;
use App\Services\RubricScoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

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
 public function verdict(Request $request, Submission $submission, RubricScoringService $scoring)
{
    $this->assertMentorCanAccessSubmission($submission);

    $quest = $submission->quest;
    abort_if(! $quest, 404);

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

    $this->syncUserRewardTotals((int) $submission->user_id);

    CacheVersion::bump('dashboard');

    return redirect()->back()->with('message', 'Verdict Processed & Rewards Calculated!');
}
    /**
     * Fitur AI Advisor (Simulasi)
     */
    public function checkWithAI(Submission $submission)
    {
        $this->assertMentorCanAccessSubmission($submission);

        $submission->load('quest');

        $rubric = $this->resolveRubricForSubmission($submission);
        if ($rubric) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI_ADVISOR_UNAVAILABLE_FOR_RUBRIC',
            ], 422);
        }

        $questTitle = $submission->quest->title;
        $studentWork = $submission->content ?? '';
        $workLength = strlen($studentWork);

        // Inisialisasi Score
        $scoreFunc = 0;
        $scoreLogic = 0;
        $scoreClean = 0;
        $aiFeedback = "";

        // Logika Analisis Sederhana
        if ($workLength < 15) {
            $scoreFunc = 10;
            $scoreLogic = 10;
            $scoreClean = 20;
            $aiFeedback = "CRITICAL_FAILURE: Bukti pengerjaan terlalu minim. Sistem mendeteksi kemungkinan bypass quest.";
        } elseif ($workLength < 100) {
            $scoreFunc = 65;
            $scoreLogic = 60;
            $scoreClean = 75;
            $aiFeedback = "ANALYSIS_PARTIAL: Implementasi dasar ditemukan untuk '{$questTitle}'. Namun, penjelasan atau struktur kode masih bisa ditingkatkan.";
        } else {
            $scoreFunc = rand(85, 100);
            $scoreLogic = rand(80, 95);
            $scoreClean = rand(85, 100);
            $aiFeedback = "ANALYSIS_SUCCESS: Data artefak untuk '{$questTitle}' tervalidasi. Struktur logika efisien dan memenuhi standar Adventurer Guild.";
        }

        return response()->json([
            'status' => 'success',
            'scores' => [
                'func' => $scoreFunc,
                'logic' => $scoreLogic,
                'neat' => $scoreClean,
                'extra' => 0,
                'att' => 0,
            ],
            'func'   => $scoreFunc,
            'logic'  => $scoreLogic,
            'clean'  => $scoreClean,
            'feedback' => $aiFeedback,
        ]);
    }

    private function syncUserRewardTotals(int $userId): void
    {
        $totals = Submission::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['Approved', 'Rejected'])
            ->selectRaw('COALESCE(SUM(earned_exp),0) as exp_total, COALESCE(SUM(earned_gold),0) as gold_total')
            ->first();

        $newExp = (int) ($totals->exp_total ?? 0);
        $newGold = (int) ($totals->gold_total ?? 0);

        $updateData = [
            'exp' => $newExp,
            'gold' => $newGold,
        ];

        if (Schema::hasColumn('users', 'lvl')) {
            $updateData['lvl'] = (int) floor($newExp / 1000) + 1;
        } elseif (Schema::hasColumn('users', 'level')) {
            $updateData['level'] = (int) floor($newExp / 1000) + 1;
        }

        User::query()->whereKey($userId)->update($updateData);
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
