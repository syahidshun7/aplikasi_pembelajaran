<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserQuestUnlock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function store(Request $request, Quest $quest)
    {
        $quest->load(['taskBank.questions' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }]);
        $this->authorizeQuestAccessForCurrentUser($quest);

        $submission = Submission::where('quest_id', $quest->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        $hasQuestUnlock = UserQuestUnlock::query()
            ->where('user_id', auth()->id())
            ->where('quest_id', $quest->id)
            ->exists();

        if (! $submission && $this->isQuestLate($quest) && ! $hasQuestUnlock) {
            throw ValidationException::withMessages([
                'content' => 'Quest sudah lewat deadline. Gunakan Time Key untuk membuka ulang quest ini.',
            ]);
        }

        $isUpdate = (bool) $submission;

        if (! $submission) {
            $submission = new Submission();
            $submission->quest_id = $quest->id;
            $submission->user_id = auth()->id();
        }

        $wasEvaluated = $this->isSubmissionEvaluated($submission);
        $isAutoChecked = $this->applyUserSubmissionPayload($request, $quest, $submission);

        $submission->save();

        if ($wasEvaluated || $this->isSubmissionEvaluated($submission)) {
            $this->syncUserRewardTotals((int) $submission->user_id);
        }

        return back()->with('message', $isAutoChecked
            ? 'AUTO_CHECK_COMPLETED_REWARD_GRANTED'
            : ($isUpdate
                ? 'MISSION_REPORT_UPDATED_RE-EVALUATING'
                : 'MISSION_REPORT_SENT_WAITING_FOR_REVIEW'));
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

        $submission->load(['quest.taskBank.questions' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }]);

        $quest = $submission->quest;
        $this->authorizeQuestAccessForCurrentUser($quest);

        $wasEvaluated = $this->isSubmissionEvaluated($submission);
        $isAutoChecked = $this->applyUserSubmissionPayload($request, $quest, $submission);

        $submission->save();

        if ($wasEvaluated || $this->isSubmissionEvaluated($submission)) {
            $this->syncUserRewardTotals((int) $submission->user_id);
        }

        return back()->with('message', $isAutoChecked
            ? 'AUTO_CHECK_UPDATED_REWARD_RECALCULATED'
            : 'MISSION_REPORT_UPDATED_RE-EVALUATING');
    }

    private function applyUserSubmissionPayload(Request $request, Quest $quest, Submission $submission): bool
    {
        if ($this->isTaskBankQuest($quest)) {
            return $this->applyTaskBankSubmissionPayload($request, $quest, $submission);
        }

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $submission->content = trim((string) $validated['content']);
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
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $answers = $this->normalizeSubmittedAnswers(
            $questions,
            (array) ($validated['task_answers'] ?? [])
        );

        $this->validateTaskBankAnswersOrFail($quest, $questions, $answers);

        if ($this->isMultipleChoiceTaskBankQuest($quest)) {
            $this->deleteSubmissionFileIfExists($submission->file_path);
            $evaluation = $this->evaluateTaskBankAnswers($quest, $answers);

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

        $submission->content = trim((string) ($validated['content'] ?? '')) ?: '[TASK_BANK_SUBMISSION]';
        $submission->file_path = $this->storeUploadedFile($request, $submission);
        $submission->status = 'Pending';
        $submission->grade = 0;
        $submission->feedback = null;
        $submission->earned_exp = 0;
        $submission->earned_gold = 0;
        $submission->scores_detail = [
            'source' => 'task_bank_submission',
            'assessment_type' => (string) ($quest->taskBank?->assessment_type ?? 'essay'),
            'total_questions' => $questions->count(),
            'answered_questions' => collect($answers)->filter(fn ($answer) => $answer !== '')->count(),
            'answers' => $answers,
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

        if ($this->isMultipleChoiceTaskBankQuest($quest)) {
            $containsNonMcq = $questions->contains(function ($question) {
                return (string) ($question->question_type ?? '') !== 'multiple_choice';
            });

            if ($containsNonMcq) {
                $errors['task_answers'] = 'Quest auto-check hanya boleh memakai soal pilihan ganda.';
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

    private function isQuestLate(Quest $quest): bool
    {
        $deadlinePassed = $quest->deadline !== null && $quest->deadline->isPast();
        $statusDone = in_array($quest->status, ['Done', 'Completed'], true);

        return $deadlinePassed || $statusDone;
    }

    private function isTaskBankQuest(Quest $quest): bool
    {
        return (bool) $quest->taskBank;
    }

    private function isMultipleChoiceTaskBankQuest(Quest $quest): bool
    {
        return (bool) ($quest->taskBank && $quest->taskBank->assessment_type === 'multiple_choice');
    }

    private function isSubmissionEvaluated(Submission $submission): bool
    {
        return in_array((string) $submission->status, ['Approved', 'Rejected'], true);
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

    private function authorizeQuestAccessForCurrentUser(Quest $quest): void
    {
        if (! $quest->study_group_id) {
            return;
        }

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
}
