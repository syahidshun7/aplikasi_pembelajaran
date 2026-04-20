<?php

namespace App\Http\Controllers;

use App\Events\DailyQuestActivityTriggered;
use App\Models\DailyQuestDefinition;
use App\Models\Submission;
use App\Models\Quest;
use App\Models\User;
use App\Services\LmsNotificationService;
use App\Services\UserRewardSyncService;
use App\Models\UserQuestUnlock;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function store(Request $request, Quest $quest, LmsNotificationService $notifications, UserRewardSyncService $rewardSync)
    {
        $quest->load(['taskBank.questions' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }]);
        $this->authorizeQuestAccessForCurrentUser($quest);

        if (! $quest->isCurrentlyVisible()) {
            throw ValidationException::withMessages([
                'content' => $this->questAvailabilityErrorMessage($quest),
            ]);
        }

        $existingSubmission = Submission::where('quest_id', $quest->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        $hasQuestUnlock = UserQuestUnlock::query()
            ->where('user_id', auth()->id())
            ->where('quest_id', $quest->id)
            ->exists();

        if (! $existingSubmission && $this->isQuestLate($quest) && ! $hasQuestUnlock) {
            throw ValidationException::withMessages([
                'content' => 'Quest sudah lewat deadline. Gunakan Time Key untuk membuka ulang quest ini.',
            ]);
        }

        $isUpdate = false;
        $submission = null;

        if ($existingSubmission) {
            if (! $this->canResubmitSubmission($existingSubmission, $quest)) {
                throw ValidationException::withMessages([
                    'submission' => 'Submission sudah diproses dan tidak bisa diubah.',
                ]);
            }

            if (! $this->isDeadlineActive($quest)) {
                throw ValidationException::withMessages([
                    'submission' => 'Deadline sudah berakhir. Submission tidak bisa diubah lagi.',
                ]);
            }

            $submission = $existingSubmission;
            $isUpdate = true;
        } else {
            $submission = new Submission();
            $submission->quest_id = $quest->id;
            $submission->user_id = auth()->id();
        }

        $wasEvaluated = $this->isSubmissionEvaluated($submission);
        $isAutoChecked = $this->applyUserSubmissionPayload($request, $quest, $submission, $isUpdate);

        $submission->save();

        if ($wasEvaluated || $this->isSubmissionEvaluated($submission)) {
            $rewardSync->sync((int) $submission->user_id);
        }

        $notifications->notifySubmissionReceived($submission);

        if (! $isUpdate && ! $this->isStaffPlayModeUser()) {
            event(new DailyQuestActivityTriggered(
                (int) $submission->user_id,
                DailyQuestDefinition::ACTIVITY_QUEST_SUBMISSION,
                1,
                [
                    'submission_uuid' => (string) $submission->uuid,
                    'quest_uuid' => (string) $quest->uuid,
                ],
            ));
        }

        CacheVersion::bump('dashboard');
        CacheVersion::bump('quests');

        return back()->with('message', $isUpdate ? 'MISSION_REPORT_UPDATED' : 'MISSION_REPORT_SENT');
    }

    public function showSubmission(Submission $submission)
    {
        $this->authorize('view', $submission);

        return Inertia::render('Quests/SubmissionDetail', [
            'submission' => [
                'id' => $submission->id,
                'uuid' => $submission->uuid,
                'status' => $submission->status,
                'content' => $submission->content,
                'file_path' => $submission->file_path,
                'feedback' => $submission->feedback,
                'submitted_at' => $submission->created_at->format('d M Y | H:i'),
                'quest' => $submission->quest,
                'grade' => $submission->grade,
                'earned_exp' => (int) ($submission->earned_exp ?? 0),
                'earned_gold' => (int) ($submission->earned_gold ?? 0),
            ],
        ]);
    }

    public function update(Request $request, $uuid)
    {
        $submission = Submission::where('uuid', $uuid)->firstOrFail();

        if ((int) $submission->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $submission->load(['quest.taskBank']);
        $quest = $submission->quest;
        $this->authorizeQuestAccessForCurrentUser($quest);

        if ($quest->taskBank) {
            abort(403, 'TASK_BANK_SUBMISSION_LOCKED');
        }

        if (! $this->canResubmitSubmission($submission, $quest)) {
            abort(403, 'SUBMISSION_ALREADY_PROCESSED');
        }

        abort_unless($this->isDeadlineActive($quest), 403, 'SUBMISSION_DEADLINE_PASSED');

        $wasEvaluated = $this->isSubmissionEvaluated($submission);
        $this->applyUserSubmissionPayload($request, $quest, $submission, true);
        $submission->save();

        if ($wasEvaluated || $this->isSubmissionEvaluated($submission)) {
            app(UserRewardSyncService::class)->sync((int) $submission->user_id);
        }

        return back()->with('message', 'MISSION_REPORT_UPDATED');
    }

    private function applyUserSubmissionPayload(Request $request, Quest $quest, Submission $submission, bool $isUpdate = false): bool
    {
        if ($this->isTaskBankQuest($quest)) {
            return $this->applyTaskBankSubmissionPayload($request, $quest, $submission);
        }

        $validated = $request->validate([
            'content' => [$isUpdate ? 'nullable' : 'required', 'string'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        if (array_key_exists('content', $validated) && $validated['content'] !== null) {
            $submission->content = trim((string) $validated['content']);
        } elseif (! $isUpdate) {
            $submission->content = '';
        }
        $submission->file_path = $this->storeUploadedFile($request, $submission);
        $submission->status = 'Pending';
        $submission->grade = 0;
        $submission->feedback = null;
        $submission->earned_exp = 0;
        $submission->earned_gold = 0;
        $submission->scores_detail = null;

        return false;
    }

    private function applyTaskBankSubmissionPayload(Request $request, Quest $quest, Submission $submission): bool
    {
        $questions = $quest->taskBank?->questions ?? collect();
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'task_answers' => 'Bank soal untuk quest ini belum memiliki soal aktif.',
            ]);
        }

        $validated = $request->validate([
            'task_answers' => ['required', 'array', 'min:1'],
            'task_answers.*' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
        ]);

        $answers = $this->normalizeSubmittedAnswers(
            $questions,
            (array) ($validated['task_answers'] ?? [])
        );

        $this->validateTaskBankAnswersOrFail($quest, $questions, $answers);

        if ($this->isMultipleChoiceTaskBankQuest($quest)) {
            $this->deleteSubmissionFileIfExists($submission->file_path);
            $evaluation = $this->evaluateTaskBankAnswers($quest, $answers);

            if ($this->isStaffPlayModeUser()) {
                $evaluation['earned_exp'] = 0;
                $evaluation['earned_gold'] = 0;
                $evaluation['scores_detail']['staff_play_mode'] = true;
                $evaluation['feedback'] .= ' STAFF_PLAY_MODE: reward tidak dihitung ke economy utama.';
            }

            $submission->content = $request->input('content') ?: '[AUTO_CHECK_TASK_BANK_SUBMISSION]';
            $submission->file_path = null;
            $submission->status = 'Approved';
            $submission->grade = $evaluation['grade'];
            $submission->feedback = $evaluation['feedback'];
            $submission->earned_exp = $evaluation['earned_exp'];
            $submission->earned_gold = $evaluation['earned_gold'];
            $submission->scores_detail = $evaluation['scores_detail'];

            return true;
        }

        $mcqAuto = $this->evaluateTaskBankMcqPortion($quest, $answers);
        $assessmentType = (string) ($quest->taskBank?->assessment_type ?? 'essay');
        $questionBankHasEssay = $questions->contains(function ($q) {
            return (string) ($q->question_type ?? '') !== 'multiple_choice';
        });
        if ($assessmentType === 'multiple_choice' && $questionBankHasEssay) {
            $assessmentType = 'mixed';
        }

        $submission->content = trim((string) ($validated['content'] ?? '')) ?: '[TASK_BANK_SUBMISSION]';
        $this->deleteSubmissionFileIfExists($submission->file_path);
        $submission->file_path = null;
        $submission->status = 'Pending';
        $submission->grade = 0;
        $submission->feedback = null;
        $submission->earned_exp = 0;
        $submission->earned_gold = 0;
        $submission->scores_detail = [
            'source' => 'task_bank_submission',
            'assessment_type' => $assessmentType,
            'total_questions' => $questions->count(),
            'answered_questions' => collect($answers)->filter(fn ($answer) => $answer !== '')->count(),
            'answers' => $answers,
            'auto_mcq' => $mcqAuto,
        ];

        return false;
    }

    private function normalizeSubmittedAnswers($questions, array $rawAnswers): array
    {
        $normalized = [];

        foreach ($questions as $question) {
            $qUuid = (string) $question->uuid;
            $value = $rawAnswers[$qUuid] ?? '';
            $normalized[$qUuid] = trim((string) $value);
        }

        return $normalized;
    }

    private function validateTaskBankAnswersOrFail(Quest $quest, $questions, array $answers): void
    {
        $errors = [];

        foreach ($questions as $question) {
            $qUuid = (string) $question->uuid;
            $answer = (string) ($answers[$qUuid] ?? '');
            $questionType = (string) ($question->question_type ?? 'essay');

            if ($answer === '') {
                $errors["task_answers.{$qUuid}"] = 'Jawaban wajib diisi untuk setiap soal.';
                continue;
            }

            if ($questionType === 'multiple_choice') {
                $options = collect($question->options_json ?? [])
                    ->map(fn ($option) => trim((string) $option))
                    ->filter(fn ($option) => $option !== '')
                    ->values()
                    ->all();

                if (count($options) < 2) {
                    $errors['task_answers'] = 'Konfigurasi soal pilihan ganda belum valid.';
                    continue;
                }

                if (! in_array($answer, $options, true)) {
                    $errors["task_answers.{$qUuid}"] = 'Jawaban tidak valid untuk opsi soal ini.';
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function storeUploadedFile(Request $request, Submission $submission): ?string
    {
        $currentPath = $submission->file_path;
        if (! $request->hasFile('file')) {
            return $currentPath;
        }

        $this->deleteSubmissionFileIfExists($currentPath);
        return $request->file('file')->store('submissions', 'public');
    }

    private function deleteSubmissionFileIfExists(?string $filePath): void
    {
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }

    private function isQuestLate(Quest $quest): bool
    {
        $deadlinePassed = $quest->deadline !== null && $quest->deadline->isPast();
        $isScheduledOnce = (string) ($quest->schedule_type ?? Quest::SCHEDULE_MANUAL) === Quest::SCHEDULE_ONCE;
        $statusDone = $isScheduledOnce && in_array((string) $quest->status, ['Done', 'Completed'], true);

        return $deadlinePassed || $statusDone;
    }

    private function isDeadlineActive(Quest $quest): bool
    {
        return $quest->deadline === null || $quest->deadline->isFuture();
    }

    private function isTaskBankQuest(Quest $quest): bool
    {
        return (bool) $quest->taskBank;
    }

    private function isMultipleChoiceTaskBankQuest(Quest $quest): bool
    {
        if (! $quest->taskBank || (string) $quest->taskBank->assessment_type !== 'multiple_choice') {
            return false;
        }

        $quest->loadMissing(['taskBank.questions' => function ($query) {
            $query->where('is_active', true);
        }]);

        $questions = $quest->taskBank?->questions ?? collect();
        $containsNonMcq = $questions->contains(function ($question) {
            return (string) ($question->question_type ?? '') !== 'multiple_choice';
        });

        // Jika ada essay/practical di bank ini, auto-check dimatikan dan flow jadi manual review.
        return ! $containsNonMcq;
    }

    private function isSubmissionEvaluated(Submission $submission): bool
    {
        return in_array((string) $submission->status, ['Approved', 'Rejected'], true);
    }

    private function canResubmitSubmission(Submission $submission, Quest $quest): bool
    {
        if (! $this->isDeadlineActive($quest)) {
            return false;
        }

        return in_array((string) $submission->status, ['Pending', 'Rejected'], true);
    }

    private function isStaffPlayModeUser(): bool
    {
        return (bool) auth()->user()?->isStaffPlayMode();
    }

    private function evaluateTaskBankAnswers(Quest $quest, array $answers): array
    {
        $questions = ($quest->taskBank?->questions ?? collect())
            ->filter(function ($question) {
                return (string) ($question->question_type ?? '') === 'multiple_choice';
            })
            ->values();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'task_answers' => 'Quest auto-check membutuhkan minimal 1 soal pilihan ganda aktif.',
            ]);
        }

        $maxWeight = (int) $questions->sum(function ($question) {
            return max(1, (int) ($question->weight ?? 1));
        });

        $correctWeight = 0;
        $correctCount = 0;

        foreach ($questions as $question) {
            $qUuid = (string) $question->uuid;
            $selected = (string) ($answers[$qUuid] ?? '');

            $weight = max(1, (int) ($question->weight ?? 1));
            $answerKey = trim((string) ($question->answer_key ?? ''));

            if ($selected !== '' && $answerKey !== '' && $selected === $answerKey) {
                $correctWeight += $weight;
                $correctCount++;
            }
        }

        $grade = $maxWeight > 0
            ? (int) round(($correctWeight / $maxWeight) * 100)
            : 0;

        $portion = $grade / 100;
        $questGold = (int) ($quest->reward_gold ?? 0);
        $questExp = (int) ($quest->reward_exp ?? 0);
        if ($questExp <= 0) {
            $questExp = $questGold;
        }

        $earnedGold = (int) floor($questGold * $portion);
        $earnedExp = (int) floor($questExp * $portion);

        return [
            'grade' => $grade,
            'earned_gold' => $earnedGold,
            'earned_exp' => $earnedExp,
            'feedback' => sprintf(
                'AUTO_CHECK_RESULT: %d/%d correct. Score %d%%. Reward +%d EXP / +%d GOLD.',
                $correctCount,
                $questions->count(),
                $grade,
                $earnedExp,
                $earnedGold
            ),
            'scores_detail' => [
                'source' => 'task_bank_auto_check',
                'assessment_type' => 'multiple_choice',
                'total_questions' => $questions->count(),
                'correct_questions' => $correctCount,
                'max_weight' => $maxWeight,
                'correct_weight' => $correctWeight,
                'answers' => $answers,
            ],
        ];
    }

    private function evaluateTaskBankMcqPortion(Quest $quest, array $answers): array
    {
        $questions = ($quest->taskBank?->questions ?? collect())
            ->filter(function ($question) {
                return (string) ($question->question_type ?? '') === 'multiple_choice';
            })
            ->values();

        $maxWeight = (int) $questions->sum(function ($question) {
            return max(0, (int) ($question->weight ?? 0));
        });

        $correctWeight = 0;
        $correctCount = 0;
        $byQuestion = [];

        foreach ($questions as $question) {
            $qUuid = (string) $question->uuid;
            $selected = (string) ($answers[$qUuid] ?? '');
            $weight = max(0, (int) ($question->weight ?? 0));
            $answerKey = trim((string) ($question->answer_key ?? ''));

            $isCorrect = $selected !== '' && $answerKey !== '' && $selected === $answerKey;
            $earned = $isCorrect ? $weight : 0;

            if ($isCorrect) {
                $correctWeight += $weight;
                $correctCount++;
            }

            $byQuestion[$qUuid] = [
                'weight' => $weight,
                'is_correct' => $isCorrect,
                'earned_points' => $earned,
                'selected' => $selected,
                'answer_key' => $answerKey,
            ];
        }

        return [
            'total_mcq_questions' => $questions->count(),
            'correct_questions' => $correctCount,
            'max_points' => $maxWeight,
            'earned_points' => $correctWeight,
            'by_question' => $byQuestion,
        ];
    }

    private function authorizeQuestAccessForCurrentUser(Quest $quest): void
    {
        if (! $quest->study_group_id) {
            return;
        }

        abort_if((bool) auth()->user()?->isStaffPlayMode(), 403, 'STAFF_PLAY_MODE_QUEST_ACCESS_DENIED');

        $userGroupIds = auth()->user()
            ->studyGroups()
            ->pluck('study_groups.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(
            in_array((int) $quest->study_group_id, $userGroupIds, true),
            403,
            'QUEST_ACCESS_DENIED'
        );
    }

    private function questAvailabilityErrorMessage(Quest $quest): string
    {
        $now = now();
        $isScheduledOnce = (string) ($quest->schedule_type ?? Quest::SCHEDULE_MANUAL) === Quest::SCHEDULE_ONCE;

        if ($isScheduledOnce && $quest->available_from && $now->lt($quest->available_from)) {
            return 'Quest ini belum masuk jadwal tayang.';
        }

        if ($isScheduledOnce && $quest->available_until && $now->gte($quest->available_until)) {
            return 'Jadwal quest ini sudah berakhir.';
        }

        return 'Quest ini sedang tidak aktif.';
    }
}
