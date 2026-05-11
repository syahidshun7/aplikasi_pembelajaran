<?php

namespace App\Services\Ai;

use App\Models\Quest;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;

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
                'content' => 'Kamu adalah AI perancang optional quest. Balas HANYA JSON valid, Bahasa Indonesia.',
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
            'title' => (string) ($payload['title'] ?? 'Optional Quest Draft'),
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
            'title' => mb_substr(trim((string) ($decoded['title'] ?? 'Optional Quest: Skill Reinforcement')), 0, 255),
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

