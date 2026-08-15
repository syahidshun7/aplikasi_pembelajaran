<?php

namespace App\Http\Controllers;

use App\Events\DailyQuestActivityTriggered;
use App\Models\DailyQuestDefinition;
use App\Models\Submission;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserQuestAttemptUnlock;
use App\Services\LmsNotificationService;
use App\Services\QuestAttemptNumberService;
use App\Services\TaskBankExamSessionService;
use App\Services\UserRewardSyncService;
use App\Models\UserQuestUnlock;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function saveExamDraft(
        Request $request,
        Quest $quest,
        TaskBankExamSessionService $examSessions,
    )
    {
        $quest->load(['taskBank.questions' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }]);
        $this->authorizeQuestAccessForCurrentUser($quest);

        abort_unless($examSessions->supports($quest), 404);

        $validated = $request->validate([
            'attempt_number' => ['required', 'integer', 'min:1'],
            'task_answers' => ['nullable', 'array'],
            'task_answers.*' => ['nullable', 'string', 'max:50000'],
            'content' => ['nullable', 'string', 'max:200000'],
        ]);

        $session = \App\Models\UserQuestAttemptSession::query()
            ->where('quest_id', $quest->id)
            ->where('user_id', $request->user()->id)
            ->where('attempt_number', (int) $validated['attempt_number'])
            ->firstOrFail();

        if ($session->submitted_at !== null) {
            return response()->json(['saved' => false, 'submitted' => true], 409);
        }

        if ($examSessions->isExpired($session)) {
            return response()->json(['saved' => false, 'expired' => true], 409);
        }

        $session->update([
            'draft_answers' => $this->collectRawTaskAnswers((array) ($validated['task_answers'] ?? [])),
            'draft_content' => (string) ($validated['content'] ?? ''),
            'draft_saved_at' => now(),
        ]);

        return response()->json([
            'saved' => true,
            'saved_at' => $session->draft_saved_at?->toISOString(),
        ]);
    }

    public function store(
        Request $request,
        Quest $quest,
        LmsNotificationService $notifications,
        UserRewardSyncService $rewardSync,
        QuestAttemptNumberService $attemptNumbers,
        TaskBankExamSessionService $examSessions,
    )
    {
        $timedOut = $request->boolean('timed_out');
        $request->validate([
            'client_submission_token' => ['nullable', 'uuid'],
            'requested_attempt_number' => ['nullable', 'integer', 'min:1'],
        ]);
        $clientSubmissionToken = (string) ($request->input('client_submission_token') ?: Str::uuid());
        $duplicateSubmission = Submission::withTrashed()
            ->where('user_id', auth()->id())
            ->where('quest_id', $quest->id)
            ->where('client_submission_token', $clientSubmissionToken)
            ->first();

        if ($duplicateSubmission) {
            return redirect()
                ->route('quests.show', $quest)
                ->with('message', 'MISSION_REPORT_ALREADY_RECEIVED');
        }

        $quest->load(['taskBank.questions' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }]);
        $this->authorizeQuestAccessForCurrentUser($quest);

        $existingSubmission = Submission::where('quest_id', $quest->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();
        $attemptCount = Submission::query()
            ->where('quest_id', $quest->id)
            ->where('user_id', auth()->id())
            ->count();
        $nextAttemptNumber = $attemptNumbers->nextForSubmission($quest->id, (int) auth()->id());
        $startNewAttempt = $request->boolean('new_attempt');
        $expectedAttemptNumber = ($existingSubmission && ! $startNewAttempt)
            ? (int) ($existingSubmission->attempt_number ?? 1)
            : $nextAttemptNumber;
        $requestedAttemptNumber = (int) $request->input('requested_attempt_number', $expectedAttemptNumber);

        if ($requestedAttemptNumber !== $expectedAttemptNumber) {
            throw ValidationException::withMessages([
                'submission' => 'Attempt ini sudah berubah atau telah dikirim. Muat ulang halaman quest.',
            ]);
        }
        $examSession = $examSessions->resolve(
            $quest,
            (int) auth()->id(),
            $expectedAttemptNumber,
        );
        if ($examSessions->isExpired($examSession) && ! $timedOut) {
            throw ValidationException::withMessages([
                'submission' => 'Waktu ujian sudah habis. Draft akan diselesaikan otomatis.',
            ]);
        }
        if ($timedOut && ! $examSessions->isExpired($examSession)) {
            throw ValidationException::withMessages([
                'submission' => 'Sesi ujian masih berjalan.',
            ]);
        }
        if ($timedOut) {
            $request->merge([
                'task_answers' => $request->has('task_answers')
                    ? $request->input('task_answers')
                    : ($examSession?->draft_answers ?? []),
                'content' => $request->has('content')
                    ? $request->input('content')
                    : (string) ($examSession?->draft_content ?? ''),
                'client_submission_token' => (string) ($examSession?->submission_token ?: $clientSubmissionToken),
            ]);
            $clientSubmissionToken = (string) $request->input('client_submission_token');
        }
        $attemptUnlock = UserQuestAttemptUnlock::query()
            ->where('user_id', auth()->id())
            ->where('quest_id', $quest->id)
            ->where('attempt_number', $nextAttemptNumber)
            ->whereNull('used_at')
            ->first();

        $hasQuestUnlock = UserQuestUnlock::query()
            ->where('user_id', auth()->id())
            ->where('quest_id', $quest->id)
            ->exists();

        if (! $quest->isCurrentlyVisible() && ! $timedOut && ! $attemptUnlock && ! $hasQuestUnlock) {
            throw ValidationException::withMessages([
                'content' => $this->questAvailabilityErrorMessage($quest),
            ]);
        }

        if (! $existingSubmission && $this->isQuestLate($quest) && ! $hasQuestUnlock && ! $timedOut) {
            throw ValidationException::withMessages([
                'content' => 'Quest sudah lewat deadline. Gunakan Time Key untuk membuka ulang quest ini.',
            ]);
        }

        $isUpdate = false;
        $submission = null;

        if ($existingSubmission && $startNewAttempt) {
            if (! $this->isSubmissionEvaluated($existingSubmission)) {
                throw ValidationException::withMessages([
                    'submission' => 'Attempt sebelumnya masih diproses.',
                ]);
            }

            if (! $quest->allowsAnotherAttempt($attemptCount, $existingSubmission) && ! $attemptUnlock) {
                throw ValidationException::withMessages([
                    'submission' => 'Batas attempt untuk quest ini sudah tercapai.',
                ]);
            }

            if (! $this->isDeadlineActive($quest) && ! $timedOut && ! $attemptUnlock) {
                throw ValidationException::withMessages([
                    'submission' => 'Deadline sudah berakhir. Attempt baru tidak tersedia.',
                ]);
            }

            $submission = new Submission();
            $submission->quest_id = $quest->id;
            $submission->user_id = auth()->id();
            $submission->attempt_number = $nextAttemptNumber;
            $submission->reward_eligible = true;
        } elseif ($existingSubmission) {
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
            $submission->attempt_number = $nextAttemptNumber;
            $submission->reward_eligible = true;
        }

        $wasEvaluated = $this->isSubmissionEvaluated($submission);
        if (! $isUpdate) {
            $submission->client_submission_token = $clientSubmissionToken;
        }
        $isAutoChecked = $this->applyUserSubmissionPayload($request, $quest, $submission, $isUpdate);
        $scoresDetail = is_array($submission->scores_detail) ? $submission->scores_detail : [];
        $submission->scores_detail = array_merge($scoresDetail, [
            'submission_mode' => $timedOut ? 'timeout' : 'manual',
            'timed_out_at' => $timedOut ? now()->toISOString() : null,
        ]);
        try {
            $submission->save();
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return redirect()
                    ->route('quests.show', $quest)
                    ->with('message', 'MISSION_REPORT_ALREADY_RECEIVED');
            }

            throw $exception;
        }
        if (! $isUpdate && $attemptUnlock) {
            $attemptUnlock->update(['used_at' => now()]);
        }
        $examSessions->markSubmitted($examSession);

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

        return redirect()
            ->route('quests.show', $quest)
            ->with('message', $isUpdate ? 'MISSION_REPORT_UPDATED' : 'MISSION_REPORT_SENT');
    }

    public function showSubmission(Submission $submission)
    {
        $this->authorize('view', $submission);

        return Inertia::render('Quests/SubmissionDetail', [
            'submission' => [
                'id' => $submission->id,
                'uuid' => $submission->uuid,
                'submission_id' => $submission->submission_id,
                'status' => $submission->status,
                'pipeline_status' => $submission->pipeline_status,
                'file_type' => $submission->file_type,
                'raw_file_saved' => $this->hasStoredRawSubmission($submission),
                'preprocess_started' => (bool) $submission->preprocess_started,
                'content' => $submission->content,
                'file_path' => $submission->file_path,
                'feedback' => $submission->feedback,
                'submitted_at' => $submission->created_at->format('d M Y | H:i'),
                'quest' => $submission->quest,
                'grade' => $submission->grade,
                'earned_exp' => (int) ($submission->earned_exp ?? 0),
                'earned_gold' => (int) ($submission->earned_gold ?? 0),
                'scores_detail' => $submission->scores_detail,
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
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'min:1', 'mimes:jpg,jpeg,png,webp,pdf,docx,txt', 'max:10240'],
        ]);

        $rawContent = (string) ($validated['content'] ?? '');
        if (trim($rawContent) === '' && ! $request->hasFile('file')) {
            throw ValidationException::withMessages([
                'content' => 'Submission harus berupa teks atau file.',
            ]);
        }

        if (trim($rawContent) !== '') {
            $submission->content = $rawContent;
        } elseif (! $isUpdate) {
            $submission->content = '';
        }

        $submission->file_path = $this->storeUploadedFile($request, $submission);
        $submission->file_type = $this->detectSubmissionFileType($request, $submission);
        $submission->pipeline_status = Submission::PIPELINE_STATUS_PENDING_PREPROCESSING;
        $submission->preprocess_started = false;
        $submission->status = Submission::STATUS_PENDING;
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
            'task_answers' => ['nullable', 'array'],
            'task_answers.*' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'min:1', 'mimes:jpg,jpeg,png,webp,pdf,docx,txt', 'max:10240'],
            'confirm_incomplete' => ['nullable', 'boolean'],
        ]);

        $assessmentType = (string) ($quest->taskBank?->assessment_type ?? 'essay');
        $rawAnswers = $this->collectRawTaskAnswers((array) ($validated['task_answers'] ?? []));
        $rawContent = (string) ($validated['content'] ?? '');

        if (
            ! $request->boolean('timed_out')
            && ! $request->boolean('confirm_incomplete')
            && trim($rawContent) === ''
            && $this->countFilledRawAnswers($rawAnswers) === 0
            && ! $request->hasFile('file')
        ) {
            throw ValidationException::withMessages([
                'content' => 'Submission harus berupa teks, jawaban, atau file.',
            ]);
        }

        $submission->content = trim($rawContent) !== '' ? $rawContent : '[TASK_BANK_RAW_SUBMISSION]';
        $submission->file_path = $this->storeUploadedFile($request, $submission);
        $submission->file_type = $this->detectSubmissionFileType($request, $submission) ?: 'text';

        if ($this->isAutoCheckedTaskBankQuest($quest)) {
            $normalizedAnswers = $this->normalizeSubmittedAnswers($questions, $rawAnswers);
            $this->validateTaskBankAnswersOrFail($quest, $questions, $normalizedAnswers);
            $result = $this->evaluateTaskBankAnswers($quest, $normalizedAnswers);

            $submission->pipeline_status = Submission::PIPELINE_STATUS_EVALUATED;
            $submission->preprocess_started = true;
            $submission->status = Submission::STATUS_APPROVED;
            $submission->grade = $result['grade'];
            $submission->feedback = $result['feedback'];
            $submission->earned_exp = $result['earned_exp'];
            $submission->earned_gold = $result['earned_gold'];
            $submission->scores_detail = $result['scores_detail'];

            return true;
        }

        $submission->pipeline_status = Submission::PIPELINE_STATUS_PENDING_PREPROCESSING;
        $submission->preprocess_started = false;
        $submission->status = Submission::STATUS_PENDING;
        $submission->grade = 0;
        $submission->feedback = null;
        $submission->earned_exp = 0;
        $submission->earned_gold = 0;
        $submission->scores_detail = [
            'source' => 'raw_task_bank_submission',
            'raw_saved' => true,
            'assessment_type' => $assessmentType,
            'total_questions' => $questions->count(),
            'answered_questions' => $this->countFilledRawAnswers($rawAnswers),
            'unanswered_questions' => max(0, $questions->count() - $this->countFilledRawAnswers($rawAnswers)),
            'answers' => $rawAnswers,
        ];

        return false;
    }

    private function collectRawTaskAnswers(array $rawAnswers): array
    {
        $answers = [];

        foreach ($rawAnswers as $questionUuid => $answer) {
            $key = (string) $questionUuid;
            if ($key === '') {
                continue;
            }

            if (is_array($answer)) {
                $answers[$key] = json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }

            $answers[$key] = (string) $answer;
        }

        return $answers;
    }

    private function countFilledRawAnswers(array $answers): int
    {
        return collect($answers)
            ->filter(fn ($answer) => trim((string) $answer) !== '')
            ->count();
    }

    private function normalizeSubmittedAnswers($questions, array $rawAnswers): array
    {
        $normalized = [];

        foreach ($questions as $question) {
            $qUuid = (string) $question->uuid;
            $value = $rawAnswers[$qUuid] ?? '';
            $answer = trim((string) $value);
            $questionType = (string) ($question->question_type ?? 'essay');

            if ($questionType === 'essay') {
                $answer = $this->sanitizeEssayAnswer($answer);
            }

            $normalized[$qUuid] = $answer;
        }

        return $normalized;
    }

    private function sanitizeEssayAnswer(string $answer): string
    {
        $cleaned = trim($answer);
        if ($cleaned === '') {
            return '';
        }

        $patterns = [
            '/(?:\n|\r)\s*(?:Q|SOAL|PERTANYAAN)\s*\d{1,3}\s*[\.:\)\-]?\s*$/iu',
            '/(?:\n|\r)\s*(?:Q|SOAL|PERTANYAAN)\s*\d{1,3}\s*\|\s*uuid=.*$/iu',
        ];

        do {
            $before = $cleaned;
            foreach ($patterns as $pattern) {
                $cleaned = preg_replace($pattern, '', $cleaned) ?? $cleaned;
            }
            $cleaned = trim($cleaned);
        } while ($cleaned !== $before);

        return $cleaned;
    }

    private function validateTaskBankAnswersOrFail(Quest $quest, $questions, array $answers): void
    {
        $errors = [];

        foreach ($questions as $question) {
            $qUuid = (string) $question->uuid;
            $answer = (string) ($answers[$qUuid] ?? '');
            $questionType = (string) ($question->question_type ?? 'essay');

            if ($answer === '' && ! in_array($questionType, ['platforming', 'word_match'], true)) {
                continue;
            }

            if ($questionType === 'multiple_choice') {
                $options = collect($question->options_json ?? [])
                    ->map(fn ($option, $index) => $this->taskOptionValue($option, (int) $index))
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
            } elseif ($questionType === 'platforming') {
                $decoded = json_decode($answer, true);
                if (! is_array($decoded) || ! isset($decoded['answers'])) {
                    $errors["task_answers.{$qUuid}"] = 'Selesaikan game platforming terlebih dahulu.';
                }
            } elseif ($questionType === 'word_match') {
                $decoded = json_decode($answer, true);
                // Allow incomplete if it's a timeout
                $isTimeout = ! empty($decoded['timeout']);
                if (! is_array($decoded) || (empty($decoded['complete']) && ! $isTimeout)) {
                    $errors["task_answers.{$qUuid}"] = 'Lengkapi semua kata yang hilang terlebih dahulu.';
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function taskOptionText(mixed $option): string
    {
        if (is_array($option)) {
            return trim((string) ($option['text'] ?? $option['label'] ?? $option['value'] ?? ''));
        }

        return trim((string) $option);
    }

    private function taskOptionValue(mixed $option, int $index = 0): string
    {
        $text = $this->taskOptionText($option);
        if ($text !== '') {
            return $text;
        }

        if (is_array($option)) {
            $key = trim((string) ($option['key'] ?? $option['value'] ?? ''));
            if ($key !== '') {
                return $key;
            }
        }

        return 'opt_' . ($index + 1);
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

    private function detectSubmissionFileType(Request $request, Submission $submission): ?string
    {
        if ($request->hasFile('file')) {
            return $this->mapFileType((string) $request->file('file')->extension());
        }

        if (trim((string) $request->input('content', '')) !== '') {
            return 'text';
        }

        return $submission->file_type;
    }

    private function hasStoredRawSubmission(Submission $submission): bool
    {
        return trim((string) $submission->content) !== '' || (bool) $submission->file_path;
    }

    private function mapFileType(string $extension): string
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tiff'], true)) {
            return 'image';
        }

        if ($extension === 'pdf') {
            return 'pdf';
        }

        if ($extension === 'docx') {
            return 'docx';
        }

        if ($extension === 'txt') {
            return 'txt';
        }

        return $extension ?: 'file';
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

    private function isAutoCheckedTaskBankQuest(Quest $quest): bool
    {
        if (! $quest->taskBank) {
            return false;
        }

        $quest->loadMissing(['taskBank.questions' => function ($query) {
            $query->where('is_active', true);
        }]);

        $questions = $quest->taskBank?->questions ?? collect();
        if ($questions->isEmpty()) {
            return false;
        }

        $containsManualCheck = $questions->contains(function ($question) {
            $qType = (string) ($question->question_type ?? '');
            return ! in_array($qType, ['multiple_choice', 'platforming', 'word_match'], true);
        });

        return ! $containsManualCheck;
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

        if (
            (string) $submission->status === Submission::STATUS_PENDING
            && in_array((string) $submission->pipeline_status, [
                Submission::PIPELINE_STATUS_PREPROCESSING,
                Submission::PIPELINE_STATUS_PREPROCESSED,
                Submission::PIPELINE_STATUS_CLEANING,
                Submission::PIPELINE_STATUS_CLEANED,
                Submission::PIPELINE_STATUS_STRUCTURE_DETECTION,
                Submission::PIPELINE_STATUS_STRUCTURED,
                Submission::PIPELINE_STATUS_SEMANTIC_ENRICHMENT,
                Submission::PIPELINE_STATUS_SEMANTIC_ENRICHED,
                Submission::PIPELINE_STATUS_AI_CHECKED,
            ], true)
        ) {
            return false;
        }

        return (string) $submission->status === Submission::STATUS_PENDING;
    }

    private function isStaffPlayModeUser(): bool
    {
        return (bool) auth()->user()?->isStaffPlayMode();
    }

    private function evaluateTaskBankAnswers(Quest $quest, array $answers): array
    {
        $questions = $quest->taskBank?->questions ?? collect();
        $assessmentType = (string) $quest->taskBank?->assessment_type;

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'task_answers' => 'Quest auto-check membutuhkan minimal 1 soal aktif.',
            ]);
        }

        $correctWeight = 0;
        $maxWeight = 0;
        $correctCount = 0;
        $totalQuestionsCount = 0;
        $answeredQuestionsCount = 0;
        $seenPlatformingPayloads = [];

        foreach ($questions as $question) {
            $qUuid = (string) $question->uuid;
            $questionType = (string) ($question->question_type ?? '');
            $weight = max(1, (int) ($question->weight ?? 1));

            if ($questionType === 'multiple_choice') {
                $selected = (string) ($answers[$qUuid] ?? '');
                $answerKey = trim((string) ($question->answer_key ?? ''));

                $maxWeight += $weight;
                $totalQuestionsCount++;
                if ($selected !== '') {
                    $answeredQuestionsCount++;
                }

                if ($selected !== '' && $answerKey !== '' && $selected === $answerKey) {
                    $correctWeight += $weight;
                    $correctCount++;
                }

                continue;
            }

            if ($questionType === 'word_match') {
                $payload = json_decode((string) ($answers[$qUuid] ?? '{}'), true) ?: [];
                $questionTotal = max(1, (int) ($payload['total'] ?? 0));
                $questionCorrect = max(0, min($questionTotal, (int) ($payload['correct_count'] ?? $payload['score'] ?? 0)));

                $maxWeight += $questionTotal;
                $correctWeight += $questionCorrect;
                $totalQuestionsCount += $questionTotal;
                $correctCount += $questionCorrect;
                $answeredQuestionsCount += $questionTotal;

                continue;
            }

            if ($questionType === 'platforming') {
                $rawPayload = (string) ($answers[$qUuid] ?? '{}');
                $payloadSignature = sha1($rawPayload);
                if (isset($seenPlatformingPayloads[$payloadSignature])) {
                    continue;
                }

                $seenPlatformingPayloads[$payloadSignature] = true;
                $payload = json_decode($rawPayload, true) ?: [];
                $questionTotal = max(1, (int) ($payload['total'] ?? 0));
                $questionCorrect = max(0, min($questionTotal, (int) ($payload['score'] ?? $payload['correct_count'] ?? 0)));

                $maxWeight += $questionTotal;
                $correctWeight += $questionCorrect;
                $totalQuestionsCount += $questionTotal;
                $correctCount += $questionCorrect;
                $answeredQuestionsCount += $questionTotal;
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
                max(1, $totalQuestionsCount),
                $grade,
                $earnedExp,
                $earnedGold
            ),
            'scores_detail' => [
                'source' => 'task_bank_auto_check',
                'assessment_type' => $assessmentType,
                'total_questions' => max(1, $totalQuestionsCount),
                'answered_questions' => $answeredQuestionsCount,
                'unanswered_questions' => max(0, $totalQuestionsCount - $answeredQuestionsCount),
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
