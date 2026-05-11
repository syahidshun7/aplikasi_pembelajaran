<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Services\Ai\OptionalQuestGeneratorService;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminOptionalQuestAiController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum generate optional quest.';

    public function generatePreview(Request $request, OptionalQuestGeneratorService $generator): JsonResponse
    {
        $validated = $request->validate([
            'study_group_id' => ['nullable', 'integer', 'exists:study_groups,id'],
            'job_id' => ['nullable', 'integer', 'exists:job_roles,id'],
            'sample_size' => ['nullable', 'integer', 'min:20', 'max:300'],
        ]);

        $this->assertMentorCanAccessScope($validated);

        $preview = $generator->generatePreview($validated);

        return response()->json([
            'status' => 'success',
            ...$preview,
        ]);
    }

    public function commitDraft(Request $request, OptionalQuestGeneratorService $generator): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'difficulty' => ['required', 'in:C-Rank,B-Rank,A-Rank,S-Rank'],
            'study_group_id' => ['nullable', 'integer', 'exists:study_groups,id'],
            'job_id' => ['nullable', 'integer', 'exists:job_roles,id'],
            'learning_objectives' => ['nullable', 'array'],
            'learning_objectives.*' => ['nullable', 'string'],
            'success_criteria' => ['nullable', 'array'],
            'success_criteria.*' => ['nullable', 'string'],
            'reasoning' => ['nullable', 'string'],
        ]);

        $this->assertMentorCanAccessScope($validated);

        $description = trim((string) ($validated['description'] ?? ''));
        $objectives = collect($validated['learning_objectives'] ?? [])
            ->filter(fn ($item) => trim((string) $item) !== '')
            ->values()
            ->all();
        $criteria = collect($validated['success_criteria'] ?? [])
            ->filter(fn ($item) => trim((string) $item) !== '')
            ->values()
            ->all();
        $reasoning = trim((string) ($validated['reasoning'] ?? ''));

        $descriptionParts = [$description];
        if (! empty($objectives)) {
            $descriptionParts[] = 'Learning Objectives: '.implode('; ', $objectives);
        }
        if (! empty($criteria)) {
            $descriptionParts[] = 'Success Criteria: '.implode('; ', $criteria);
        }
        if ($reasoning !== '') {
            $descriptionParts[] = 'Reasoning: '.$reasoning;
        }

        $quest = $generator->commitDraft([
            'title' => $validated['title'],
            'description' => implode("\n\n", $descriptionParts),
            'difficulty' => $validated['difficulty'],
            'study_group_id' => $validated['study_group_id'] ?? null,
        ]);

        CacheVersion::bump('quests');
        CacheVersion::bump('home');

        return response()->json([
            'status' => 'success',
            'message' => 'OPTIONAL_QUEST_DRAFT_COMMITTED',
            'quest' => [
                'id' => (int) $quest->id,
                'uuid' => (string) $quest->uuid,
                'title' => (string) $quest->title,
                'quest_type' => (string) $quest->quest_type,
                'status' => (string) $quest->status,
                'schedule_type' => (string) $quest->schedule_type,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertMentorCanAccessScope(array $payload): void
    {
        $user = auth()->user();
        if (! $user?->isMentor()) {
            return;
        }

        $mentorJobId = (int) ($user->job_id ?? 0);
        abort_if($mentorJobId <= 0, 403, self::MENTOR_JOB_REQUIRED_MESSAGE);

        $payloadJobId = (int) ($payload['job_id'] ?? 0);
        if ($payloadJobId > 0 && $payloadJobId !== $mentorJobId) {
            throw ValidationException::withMessages([
                'job_id' => 'Mentor hanya boleh generate optional quest untuk jurusannya sendiri.',
            ]);
        }

        $studyGroupId = (int) ($payload['study_group_id'] ?? 0);
        if ($studyGroupId <= 0) {
            return;
        }

        $validGroup = StudyGroup::query()
            ->whereKey($studyGroupId)
            ->where('job_id', $mentorJobId)
            ->exists();

        if (! $validGroup) {
            throw ValidationException::withMessages([
                'study_group_id' => 'Study group tidak sesuai jurusan mentor.',
            ]);
        }
    }
}
