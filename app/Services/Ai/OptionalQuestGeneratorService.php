<?php

namespace App\Services\Ai;

use App\Models\Quest;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\TaskQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OptionalQuestGeneratorService
{
    public function __construct(
        private readonly AiProviderGateway $providerGateway,
        private readonly AiResponseJsonParser $jsonParser,
        private readonly AiDataMaskingService $maskingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function generatePreview(array $filters = []): array
    {
        $sampleSize = max(20, min(300, (int) ($filters['sample_size'] ?? 120)));
        $studyGroupId = (int) ($filters['study_group_id'] ?? 0);
        $jobId = (int) ($filters['job_id'] ?? 0);

        $query = Submission::query()
            ->with([
                'quest:id,title,difficulty,study_group_id,quest_type',
                'quest.studyGroup:id,name,job_id',
                'user:id,job_id',
            ])
            ->whereIn('status', ['Approved', 'Rejected'])
            ->orderByDesc('id')
            ->limit($sampleSize);

        $this->applyFilters($query, $studyGroupId, $jobId);

        $rows = $query->get();

        $approved = $rows->where('status', 'Approved');
        $rejected = $rows->where('status', 'Rejected');

        $avgGrade = (float) round((float) $approved->avg(fn ($submission) => (int) ($submission->grade ?? 0)), 2);

        $lowQualityApproved = $approved
            ->filter(fn ($submission) => (int) ($submission->grade ?? 0) <= 75)
            ->take(15)
            ->values();

        $weakSignals = $lowQualityApproved
            ->map(function (Submission $submission) {
                return [
                    'quest' => (string) ($submission->quest?->title ?? '-'),
                    'grade' => (int) ($submission->grade ?? 0),
                    'feedback' => $this->maskingService->maskText((string) ($submission->feedback ?? '')),
                    'content_excerpt' => $this->maskingService->maskText(mb_substr((string) ($submission->content ?? ''), 0, 200)),
                ];
            })
            ->values()
            ->all();

        $messages = [
            [
                'role' => 'system',
                'content' => 'Kamu adalah AI perancang side quest. Balas HANYA JSON valid, Bahasa Indonesia.',
            ],
            [
                'role' => 'user',
                'content' => $this->buildGenerationPrompt([
                    'scope' => [
                        'study_group_id' => $studyGroupId > 0 ? $studyGroupId : null,
                        'job_id' => $jobId > 0 ? $jobId : null,
                    ],
                    'sample_size' => $rows->count(),
                    'approved_count' => $approved->count(),
                    'rejected_count' => $rejected->count(),
                    'approved_avg_grade' => $avgGrade,
                    'weak_signals' => $weakSignals,
                ]),
            ],
        ];

        $providerResult = $this->providerGateway->chat($messages);
        $decoded = $this->jsonParser->decode((string) ($providerResult['content'] ?? ''));

        $draft = $this->normalizeDraft($decoded);

        return [
            'draft' => $draft,
            'insight' => [
                'sample_size' => $rows->count(),
                'approved_count' => $approved->count(),
                'rejected_count' => $rejected->count(),
                'approved_avg_grade' => $avgGrade,
            ],
            'provider_used' => (string) $providerResult['provider_used'],
            'is_fallback' => (bool) $providerResult['is_fallback'],
            'latency_ms' => (int) $providerResult['latency_ms'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function commitDraft(array $payload): Quest
    {
        $difficulty = (string) ($payload['difficulty'] ?? 'C-Rank');
        $reward = $this->rewardForDifficulty($difficulty);

        return Quest::query()->create([
            'title' => (string) ($payload['title'] ?? 'Side Quest Draft'),
            'description' => (string) ($payload['description'] ?? ''),
            'difficulty' => $difficulty,
            'reward_gold' => $reward,
            'reward_exp' => $reward,
            'quest_type' => Quest::TYPE_OPTIONAL,
            'status' => Quest::STATUS_IN_PROGRESS,
            'schedule_type' => Quest::SCHEDULE_MANUAL,
            'study_group_id' => $payload['study_group_id'] ?? null,
            'task_bank_id' => null,
            'rubric_id' => null,
            'deadline' => null,
            'available_from' => null,
            'available_until' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generateFromTheme(array $payload): array
    {
        $theme = trim((string) ($payload['theme'] ?? ''));
        $aiNote = trim((string) ($payload['ai_note'] ?? ''));
        $questionType = (string) ($payload['question_type'] ?? 'mixed');
        $questionCount = max(3, min(30, (int) ($payload['question_count'] ?? 10)));
        $difficulty = (string) ($payload['difficulty'] ?? 'C-Rank');
        if (! in_array($difficulty, ['C-Rank', 'B-Rank', 'A-Rank', 'S-Rank'], true)) {
            $difficulty = 'C-Rank';
        }
        if (! in_array($questionType, ['multiple_choice', 'essay', 'mixed', 'platforming', 'word_match'], true)) {
            $questionType = 'mixed';
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Kamu adalah AI perancang side quest + task bank. Balas HANYA JSON valid, Bahasa Indonesia.',
            ],
            [
                'role' => 'user',
                'content' => $this->buildThemePrompt([
                    'theme' => $theme,
                    'ai_note' => $aiNote,
                    'question_type' => $questionType,
                    'question_count' => $questionCount,
                    'difficulty' => $difficulty,
                    'study_group_id' => (int) ($payload['study_group_id'] ?? 0) ?: null,
                    'job_id' => (int) ($payload['job_id'] ?? 0) ?: null,
                ]),
            ],
        ];

        $providerResult = $this->providerGateway->chat($messages);
        $decoded = $this->jsonParser->decode((string) ($providerResult['content'] ?? ''));
        $bundle = $this->normalizeThemeBundle(is_array($decoded) ? $decoded : [], $questionType, $questionCount, $difficulty);

        return [
            'bundle' => $bundle,
            'provider_used' => (string) $providerResult['provider_used'],
            'is_fallback' => (bool) $providerResult['is_fallback'],
            'latency_ms' => (int) $providerResult['latency_ms'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function commitFromTheme(array $payload): Quest
    {
        $bundle = (array) ($payload['bundle'] ?? []);
        $quest = (array) ($bundle['quest'] ?? []);
        $taskBank = (array) ($bundle['task_bank'] ?? []);
        $questions = array_values(array_filter((array) ($bundle['questions'] ?? []), 'is_array'));

        $difficulty = (string) ($quest['difficulty'] ?? 'C-Rank');
        if (! in_array($difficulty, ['C-Rank', 'B-Rank', 'A-Rank', 'S-Rank'], true)) {
            $difficulty = 'C-Rank';
        }

        $assessmentType = (string) ($taskBank['assessment_type'] ?? 'mixed');
        if (! in_array($assessmentType, ['multiple_choice', 'essay', 'mixed', 'platforming', 'word_match'], true)) {
            $assessmentType = 'mixed';
        }

        $publishMode = (string) ($payload['publish_mode'] ?? 'schedule');
        $availableFrom = $this->parseDateTime($payload['available_from'] ?? null);
        $availableUntil = $this->parseDateTime($payload['available_until'] ?? null);
        $deadline = $this->parseDateTime($payload['deadline'] ?? null);

        $scheduleType = Quest::SCHEDULE_MANUAL;
        $status = Quest::STATUS_IN_PROGRESS;

        if ($publishMode === 'publish_now') {
            $status = Quest::STATUS_AVAILABLE;
        } elseif ($publishMode === 'schedule' && $availableFrom instanceof Carbon) {
            $scheduleType = Quest::SCHEDULE_ONCE;
            $status = Quest::STATUS_AVAILABLE;
        }

        return DB::transaction(function () use ($payload, $quest, $taskBank, $questions, $difficulty, $assessmentType, $scheduleType, $status, $availableFrom, $availableUntil, $deadline) {
            $taskBankModel = TaskBank::query()->create([
                'name' => mb_substr(trim((string) ($taskBank['name'] ?? 'AI Task Bank')), 0, 255),
                'description' => trim((string) ($taskBank['description'] ?? '')),
                'assessment_type' => $assessmentType,
                'is_active' => true,
                'job_role_id' => (int) ($payload['job_id'] ?? 0) ?: null,
            ]);

            foreach ($questions as $index => $question) {
                $type = (string) ($question['question_type'] ?? 'essay');
                if (! in_array($type, ['multiple_choice', 'essay', 'platforming', 'word_match'], true)) {
                    $type = 'essay';
                }

                $options = [];
                $answerKey = (string) ($question['answer_key'] ?? '');
                if ($type === 'multiple_choice') {
                    $options = array_values(array_filter(
                        array_map('trim', array_map('strval', (array) ($question['options'] ?? []))),
                        fn ($opt) => $opt !== ''
                    ));
                    if (count($options) < 2) {
                        continue;
                    }
                    if ($answerKey === '' || ! in_array($answerKey, $options, true)) {
                        $answerKey = $options[0];
                    }
                }

                $extraConfig = null;
                if ($type === 'platforming' && ! empty($question['platforming_config'])) {
                    $extraConfig = $question['platforming_config'];
                } elseif ($type === 'word_match' && ! empty($question['word_match_config'])) {
                    $extraConfig = $question['word_match_config'];
                }

                TaskQuestion::query()->create([
                    'task_bank_id' => $taskBankModel->id,
                    'question_text' => mb_substr(trim((string) ($question['question_text'] ?? '')), 0, 2000),
                    'question_type' => $type,
                    'options_json' => $type === 'multiple_choice' ? $options : ($extraConfig !== null ? $extraConfig : null),
                    'answer_key' => $type === 'multiple_choice' ? $answerKey : '',
                    'weight' => max(1, (int) ($question['weight'] ?? 1)),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
            }

            $reward = $this->rewardForDifficulty($difficulty);

            $questType = (string) ($payload['quest_type'] ?? Quest::TYPE_OPTIONAL);
            if (! in_array($questType, [Quest::TYPE_MAIN, Quest::TYPE_OPTIONAL], true)) {
                $questType = Quest::TYPE_OPTIONAL;
            }

            return Quest::query()->create([
                'title' => mb_substr(trim((string) ($quest['title'] ?? 'Side Quest AI')), 0, 255),
                'description' => trim((string) ($quest['description'] ?? '')),
                'difficulty' => $difficulty,
                'reward_gold' => $reward,
                'reward_exp' => $reward,
                'quest_type' => $questType,
                'status' => $status,
                'schedule_type' => $scheduleType,
                'study_group_id' => (int) ($payload['study_group_id'] ?? 0) ?: null,
                'task_bank_id' => $taskBankModel->id,
                'rubric_id' => null,
                'deadline' => $deadline,
                'available_from' => $availableFrom,
                'available_until' => $availableUntil,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildThemePrompt(array $input): string
    {
        $schema = match ($input['question_type']) {
            'multiple_choice' => '{"question_text": "string", "question_type": "multiple_choice", "options": ["string"], "answer_key": "string", "weight": 1}',
            'essay'           => '{"question_text": "string", "question_type": "essay", "weight": 1}',
            'platforming'     => '{"question_text": "string (judul level/stage)", "question_type": "platforming", "platforming_config": {"stages": [{"prompt": "string (pertanyaan singkat)", "correct_answer": "string (jawaban benar)", "wrong_answers": ["string", "string"]}], "time_limit": 60}, "weight": 1}',
            'word_match'      => '{"question_text": "string (kalimat asli lengkap)", "question_type": "word_match", "word_match_config": {"sentence": "string (kalimat dengan ___ menggantikan setiap kata yang dihapus)", "blanks": ["string (kata yang dihapus, urutan sesuai ___)", "string"], "distractors": ["string (kata pengecoh yang mirip tapi salah)", "string"]}, "weight": 1}',
            default           => '{"question_text": "string", "question_type": "multiple_choice|essay", "options": ["string"], "answer_key": "string", "weight": 1}',
        };

        $assessmentType = match ($input['question_type']) {
            'multiple_choice', 'essay', 'platforming', 'word_match' => $input['question_type'],
            default => 'mixed',
        };

        $importantNote = trim((string) ($input['ai_note'] ?? ''));

        return implode("\n", [
            'Buat 1 SIDE quest lengkap dengan task bank dan daftar soal berdasarkan input:',
            json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $importantNote !== ''
                ? 'Catatan penting (WAJIB diprioritaskan): '.$importantNote
                : 'Catatan penting (WAJIB diprioritaskan): -',
            '',
            'Output JSON schema:',
            '{',
            '  "quest": {"title": "string max 255", "description": "string", "difficulty": "C-Rank|B-Rank|A-Rank|S-Rank", "learning_objectives": ["string"], "success_criteria": ["string"], "reasoning": "string"},',
            '  "task_bank": {"name": "string", "description": "string", "assessment_type": "'.$assessmentType.'"},',
            '  "questions": ['.$schema.']',
            '}',
            'Aturan:',
            '- Bahasa Indonesia, praktis, fokus pada tema.',
            '- question_count wajib sama dengan input.',
            '- Untuk multiple_choice: minimal 3 opsi, answer_key harus persis salah satu opsi.',
            '- Jangan menyertakan indeks huruf (A,B,C) di answer_key; isi teks opsi.',
            '- Untuk essay: biarkan options kosong dan answer_key kosong.',
            '- Jika question_type=mixed, campurkan rasio seimbang.',
            '- Untuk platforming: setiap soal berisi stages berupa array. Tiap stage wajib punya field: "prompt" (teks pertanyaan singkat), "correct_answer" (jawaban benar, string), "wrong_answers" (array 2-3 jawaban salah). JANGAN gunakan field "question" — wajib "prompt".',
            '- Untuk word_match: soal berupa kalimat dengan kata-kata yang dihapus (ganti dengan ___). Isi word_match_config.sentence (kalimat dengan ___), word_match_config.blanks (daftar kata yang dihapus, urutan sesuai posisi ___), dan word_match_config.distractors (2-4 kata pengecoh yang mirip tapi salah — WAJIB ada).',
            '- Jika ada catatan penting, jadikan itu prioritas utama saat menyusun tingkat kesulitan dan gaya soal.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function normalizeThemeBundle(array $decoded, string $questionType, int $questionCount, string $difficulty): array
    {
        $quest = (array) ($decoded['quest'] ?? []);
        $taskBank = (array) ($decoded['task_bank'] ?? []);
        $questions = array_values(array_filter((array) ($decoded['questions'] ?? []), 'is_array'));

        $questDifficulty = (string) ($quest['difficulty'] ?? $difficulty);
        if (! in_array($questDifficulty, ['C-Rank', 'B-Rank', 'A-Rank', 'S-Rank'], true)) {
            $questDifficulty = $difficulty;
        }

        $assessmentType = (string) ($taskBank['assessment_type'] ?? $questionType);
        if (! in_array($assessmentType, ['multiple_choice', 'essay', 'mixed', 'platforming', 'word_match'], true)) {
            $assessmentType = $questionType;
        }

        $normalizedQuestions = [];
        foreach ($questions as $question) {
            $type = (string) ($question['question_type'] ?? 'essay');
            if (in_array($questionType, ['multiple_choice', 'essay', 'platforming', 'word_match'], true)) {
                $type = $questionType;
            } elseif (! in_array($type, ['multiple_choice', 'essay', 'platforming', 'word_match'], true)) {
                $type = 'essay';
            }

            $options = $type === 'multiple_choice'
                ? array_values(array_filter(array_map('trim', array_map('strval', (array) ($question['options'] ?? []))), fn ($opt) => $opt !== ''))
                : [];
            $answerKey = $type === 'multiple_choice' ? (string) ($question['answer_key'] ?? '') : '';
            if ($type === 'multiple_choice' && $answerKey !== '' && ! in_array($answerKey, $options, true)) {
                $answerKey = $options[0] ?? '';
            }

            $entry = [
                'question_text' => mb_substr(trim((string) ($question['question_text'] ?? '')), 0, 2000),
                'question_type' => $type,
                'options' => $options,
                'answer_key' => $answerKey,
                'weight' => max(1, (int) ($question['weight'] ?? 1)),
            ];

            if ($type === 'platforming' && isset($question['platforming_config'])) {
                $entry['platforming_config'] = $question['platforming_config'];
            }

            if ($type === 'word_match' && isset($question['word_match_config'])) {
                $entry['word_match_config'] = $question['word_match_config'];
            }

            $normalizedQuestions[] = $entry;
        }

        if (count($normalizedQuestions) > $questionCount) {
            $normalizedQuestions = array_slice($normalizedQuestions, 0, $questionCount);
        }

        return [
            'quest' => [
                'title' => mb_substr(trim((string) ($quest['title'] ?? 'Side Quest AI')), 0, 255),
                'description' => trim((string) ($quest['description'] ?? '')),
                'difficulty' => $questDifficulty,
                'learning_objectives' => $this->normalizeStringList($quest['learning_objectives'] ?? []),
                'success_criteria' => $this->normalizeStringList($quest['success_criteria'] ?? []),
                'reasoning' => trim((string) ($quest['reasoning'] ?? '')),
            ],
            'task_bank' => [
                'name' => mb_substr(trim((string) ($taskBank['name'] ?? 'AI Task Bank')), 0, 255),
                'description' => trim((string) ($taskBank['description'] ?? '')),
                'assessment_type' => $assessmentType,
            ],
            'questions' => $normalizedQuestions,
            'question_count' => count($normalizedQuestions),
        ];
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyFilters(Builder $query, int $studyGroupId, int $jobId): void
    {
        if ($studyGroupId > 0) {
            $query->whereHas('quest', fn (Builder $questQuery) => $questQuery->where('study_group_id', $studyGroupId));
        }

        if ($jobId > 0) {
            $query->where(function (Builder $scope) use ($jobId) {
                $scope
                    ->whereHas('user', fn (Builder $userQuery) => $userQuery->where('job_id', $jobId))
                    ->orWhereHas('quest.studyGroup', fn (Builder $groupQuery) => $groupQuery->where('job_id', $jobId));
            });
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildGenerationPrompt(array $input): string
    {
        return implode("\n", [
            'Buat 1 draft OPTIONAL quest berdasarkan pola submission berikut:',
            json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            '',
            'Output JSON schema:',
            '{',
            '  "title": "string max 255",',
            '  "description": "string",',
            '  "difficulty": "C-Rank|B-Rank|A-Rank|S-Rank",',
            '  "learning_objectives": ["string"],',
            '  "success_criteria": ["string"],',
            '  "reasoning": "string"',
            '}',
            'Aturan: quest harus praktis, fokus memperbaiki gap umum, dan feasible 30-90 menit.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function normalizeDraft(array $decoded): array
    {
        $difficulty = (string) ($decoded['difficulty'] ?? 'C-Rank');
        if (! in_array($difficulty, ['C-Rank', 'B-Rank', 'A-Rank', 'S-Rank'], true)) {
            $difficulty = 'C-Rank';
        }

        $learningObjectives = $this->normalizeStringList($decoded['learning_objectives'] ?? []);
        $successCriteria = $this->normalizeStringList($decoded['success_criteria'] ?? []);

        $description = trim((string) ($decoded['description'] ?? ''));
        if ($description === '') {
            $description = 'Quest tambahan untuk memperkuat area yang masih sering lemah pada submission terbaru.';
        }

        return [
            'title' => mb_substr(trim((string) ($decoded['title'] ?? 'Side Quest: Skill Reinforcement')), 0, 255),
            'description' => $description,
            'difficulty' => $difficulty,
            'learning_objectives' => $learningObjectives,
            'success_criteria' => $successCriteria,
            'reasoning' => trim((string) ($decoded['reasoning'] ?? 'Generated from recent submission pattern.')),
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();
    }

    private function rewardForDifficulty(string $difficulty): int
    {
        return [
            'S-Rank' => 5000,
            'A-Rank' => 2500,
            'B-Rank' => 1000,
            'C-Rank' => 500,
        ][$difficulty] ?? 500;
    }
}
