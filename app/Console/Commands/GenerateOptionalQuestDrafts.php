<?php

namespace App\Console\Commands;

use App\Models\JobRole;
use App\Models\Quest;
use App\Models\StudyGroup;
use App\Services\Ai\OptionalQuestGeneratorService;
use Illuminate\Console\Command;

class GenerateOptionalQuestDrafts extends Command
{
    protected $signature = 'ai:optional-quests:generate-drafts
        {--job-id= : Target specific job role id}
        {--study-group-id= : Target specific study group id}
        {--sample-size=120 : Submission sample size per draft}
        {--max-drafts=3 : Max draft commits in one run}
        {--theme= : Generate directly from theme}
        {--question-type=mixed : multiple_choice|essay|mixed}
        {--question-count=10 : Number of generated questions}
        {--difficulty=C-Rank : C-Rank|B-Rank|A-Rank|S-Rank}
        {--publish-mode=draft : draft|publish_now|schedule}
        {--publish-at= : Scheduled publish datetime}
        {--available-until= : Optional availability end datetime}
        {--deadline= : Optional quest deadline}
        {--dry-run : Generate preview only, without saving draft quests}';

    protected $description = 'Generate side quest drafts from submission patterns using AI advisor pipeline';

    public function handle(OptionalQuestGeneratorService $generator): int
    {
        $theme = trim((string) ($this->option('theme') ?? ''));
        if ($theme !== '') {
            return $this->handleThemeMode($generator, $theme);
        }

        $sampleSize = max(20, min(300, (int) $this->option('sample-size')));
        $maxDrafts = max(1, min(20, (int) $this->option('max-drafts')));
        $dryRun = (bool) $this->option('dry-run');

        $scopes = $this->resolveScopes($maxDrafts);
        if (empty($scopes)) {
            $this->warn('No scope resolved. Aborting.');
            return self::SUCCESS;
        }

        $created = 0;
        $processed = 0;

        foreach ($scopes as $scope) {
            if ($processed >= $maxDrafts) {
                break;
            }

            $processed++;
            $scopeLabel = sprintf(
                'job=%s, study_group=%s',
                (string) ($scope['job_id'] ?? 'all'),
                (string) ($scope['study_group_id'] ?? 'all')
            );

            $this->line("Generating draft preview for scope: {$scopeLabel}");

            $preview = $generator->generatePreview([
                'job_id' => $scope['job_id'] ?? null,
                'study_group_id' => $scope['study_group_id'] ?? null,
                'sample_size' => $sampleSize,
            ]);

            $draft = $preview['draft'] ?? [];
            $title = trim((string) ($draft['title'] ?? 'Side Quest Draft'));

            if ($dryRun) {
                $this->table(['TITLE', 'DIFFICULTY', 'PROVIDER', 'FALLBACK'], [[
                    $title,
                    (string) ($draft['difficulty'] ?? 'C-Rank'),
                    (string) ($preview['provider_used'] ?? '-'),
                    (bool) ($preview['is_fallback'] ?? false) ? 'yes' : 'no',
                ]]);
                continue;
            }

            if ($this->hasRecentDraftDuplicate($title, $scope['study_group_id'] ?? null)) {
                $this->warn('Skipped duplicate draft title in last 7 days: '.$title);
                continue;
            }

            $description = $this->buildDescriptionFromDraft($draft);

            $quest = $generator->commitDraft([
                'title' => $title,
                'description' => $description,
                'difficulty' => (string) ($draft['difficulty'] ?? 'C-Rank'),
                'study_group_id' => $scope['study_group_id'] ?? null,
            ]);

            $created++;
            $this->info("Draft committed: {$quest->title} ({$quest->uuid})");
        }

        if ($dryRun) {
            $this->info("DRY_RUN_COMPLETE. Processed scopes: {$processed}");
            return self::SUCCESS;
        }

        $this->info("OPTIONAL_QUEST_DRAFTS_CREATED: {$created}");

        return self::SUCCESS;
    }

    private function handleThemeMode(OptionalQuestGeneratorService $generator, string $theme): int
    {
        $questionType = (string) $this->option('question-type');
        $questionCount = max(3, min(30, (int) $this->option('question-count')));
        $difficulty = (string) $this->option('difficulty');
        $publishMode = (string) $this->option('publish-mode');
        $dryRun = (bool) $this->option('dry-run');
        $jobId = (int) ($this->option('job-id') ?? 0) ?: null;
        $studyGroupId = (int) ($this->option('study-group-id') ?? 0) ?: null;

        $preview = $generator->generateFromTheme([
            'theme' => $theme,
            'question_type' => $questionType,
            'question_count' => $questionCount,
            'difficulty' => $difficulty,
            'job_id' => $jobId,
            'study_group_id' => $studyGroupId,
        ]);

        $bundle = $preview['bundle'] ?? [];
        $this->table(['TITLE', 'DIFFICULTY', 'Q_COUNT', 'ASSESSMENT'], [[
            (string) data_get($bundle, 'quest.title', '-'),
            (string) data_get($bundle, 'quest.difficulty', '-'),
            (int) data_get($bundle, 'question_count', 0),
            (string) data_get($bundle, 'task_bank.assessment_type', '-'),
        ]]);

        if ($dryRun) {
            $this->info('THEME_MODE_DRY_RUN_COMPLETE');
            return self::SUCCESS;
        }

        $quest = $generator->commitFromTheme([
            'bundle' => $bundle,
            'publish_mode' => $publishMode,
            'available_from' => (string) ($this->option('publish-at') ?? ''),
            'available_until' => (string) ($this->option('available-until') ?? ''),
            'deadline' => (string) ($this->option('deadline') ?? ''),
            'job_id' => $jobId,
            'study_group_id' => $studyGroupId,
        ]);

        $this->info("THEME_QUEST_COMMITTED: {$quest->title} ({$quest->uuid})");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{job_id:int|null,study_group_id:int|null}>
     */
    private function resolveScopes(int $maxDrafts): array
    {
        $jobId = (int) ($this->option('job-id') ?? 0);
        $studyGroupId = (int) ($this->option('study-group-id') ?? 0);

        if ($studyGroupId > 0) {
            $group = StudyGroup::query()->find($studyGroupId);
            if (! $group) {
                return [];
            }

            return [[
                'job_id' => (int) ($group->job_id ?: 0) ?: null,
                'study_group_id' => (int) $group->id,
            ]];
        }

        if ($jobId > 0) {
            return [[
                'job_id' => $jobId,
                'study_group_id' => null,
            ]];
        }

        $jobIds = JobRole::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($jobIds)) {
            return [[
                'job_id' => null,
                'study_group_id' => null,
            ]];
        }

        return collect($jobIds)
            ->take($maxDrafts)
            ->map(fn ($id) => [
                'job_id' => $id,
                'study_group_id' => null,
            ])
            ->values()
            ->all();
    }

    private function hasRecentDraftDuplicate(string $title, ?int $studyGroupId): bool
    {
        $normalizedTitle = trim($title);
        if ($normalizedTitle === '') {
            return false;
        }

        return Quest::query()
            ->where('quest_type', Quest::TYPE_OPTIONAL)
            ->where('status', Quest::STATUS_IN_PROGRESS)
            ->where('title', $normalizedTitle)
            ->where('study_group_id', $studyGroupId)
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function buildDescriptionFromDraft(array $draft): string
    {
        $description = trim((string) ($draft['description'] ?? ''));

        $objectives = collect($draft['learning_objectives'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();

        $criteria = collect($draft['success_criteria'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();

        $reasoning = trim((string) ($draft['reasoning'] ?? ''));

        $parts = [$description !== '' ? $description : 'Quest optional draft generated automatically.'];
        if (! empty($objectives)) {
            $parts[] = 'Learning Objectives: '.implode('; ', $objectives);
        }
        if (! empty($criteria)) {
            $parts[] = 'Success Criteria: '.implode('; ', $criteria);
        }
        if ($reasoning !== '') {
            $parts[] = 'Reasoning: '.$reasoning;
        }

        return implode("\n\n", $parts);
    }
}
