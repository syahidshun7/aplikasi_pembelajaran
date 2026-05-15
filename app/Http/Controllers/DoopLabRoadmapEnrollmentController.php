<?php

namespace App\Http\Controllers;

use App\Models\DoopLabRoadmap;
use App\Models\DoopLabRoadmapEdge;
use App\Models\DoopLabRoadmapEnrollment;
use App\Models\DoopLabRoadmapNode;
use App\Models\DoopLabRoadmapNodeProgress;
use App\Models\Guide;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DoopLabRoadmapEnrollmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $enrollments = DoopLabRoadmapEnrollment::query()
            ->where('user_id', (int) $user->id)
            ->with(['roadmap:id,uuid,title,description', 'mentor:id,name'])
            ->latest('updated_at')
            ->get()
            ->map(fn (DoopLabRoadmapEnrollment $enrollment) => [
                'uuid' => (string) $enrollment->uuid,
                'status' => (string) $enrollment->status,
                'roadmap' => [
                    'uuid' => (string) ($enrollment->roadmap?->uuid ?? ''),
                    'title' => (string) ($enrollment->roadmap?->title ?? ''),
                    'description' => (string) ($enrollment->roadmap?->description ?? ''),
                ],
                'mentor_name' => (string) ($enrollment->mentor?->name ?? ''),
                'updated_at' => optional($enrollment->updated_at)->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('DoopLab/Roadmaps/MyPaths', [
            'enrollments' => $enrollments,
        ]);
    }

    public function show(Request $request, DoopLabRoadmapEnrollment $enrollment): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $isOwner = (int) $enrollment->user_id === (int) $user->id;
        $isMentor = (int) $enrollment->mentor_user_id === (int) $user->id;
        abort_unless($isOwner || $isMentor || $user->isAdmin(), 403, 'ROADMAP_ENROLLMENT_FORBIDDEN');

        $enrollment->load([
            'roadmap.sections',
            'roadmap.nodes.resources',
            'roadmap.edges.fromNode:id,uuid',
            'roadmap.edges.toNode:id,uuid',
            'user:id,name',
            'mentor:id,name',
        ]);

        $this->ensureProgressRows($enrollment);
        $this->recomputeUnlocks($enrollment);

        $progressMap = $enrollment->nodeProgress()->get()->keyBy('node_id');

        $serialized = [
            'uuid' => (string) $enrollment->uuid,
            'status' => (string) $enrollment->status,
            'is_owner' => $isOwner,
            'is_mentor' => $isMentor,
            'mentor_name' => (string) ($enrollment->mentor?->name ?? ''),
            'student_name' => (string) ($enrollment->user?->name ?? ''),
            'roadmap' => [
                'uuid' => (string) ($enrollment->roadmap?->uuid ?? ''),
                'title' => (string) ($enrollment->roadmap?->title ?? ''),
                'description' => (string) ($enrollment->roadmap?->description ?? ''),
                'sections' => $enrollment->roadmap?->sections?->map(fn ($section) => [
                    'uuid' => (string) $section->uuid,
                    'title' => (string) $section->title,
                    'x' => (int) $section->x,
                    'y' => (int) $section->y,
                    'width' => (int) $section->width,
                    'height' => (int) $section->height,
                    'bg_color' => (string) $section->bg_color,
                    'text_color' => (string) $section->text_color,
                    'font_size' => (int) ($section->font_size ?? 20),
                    'text_align' => (string) ($section->text_align ?? 'left'),
                    'text_valign' => (string) ($section->text_valign ?? 'top'),
                ])->values()->all() ?? [],
                'nodes' => $enrollment->roadmap?->nodes?->map(function ($node) use ($progressMap) {
                    $progress = $progressMap->get((int) $node->id);
                    $resourceMetaList = $this->resolveResourceMetaList($node);

                    return [
                        'uuid' => (string) $node->uuid,
                        'title' => (string) $node->title,
                        'x' => (int) $node->x,
                        'y' => (int) $node->y,
                        'width' => (int) $node->width,
                        'height' => (int) $node->height,
                        'bg_color' => (string) $node->bg_color,
                        'text_color' => (string) $node->text_color,
                        'font_size' => (int) ($node->font_size ?? 28),
                        'text_align' => (string) ($node->text_align ?? 'center'),
                        'text_valign' => (string) ($node->text_valign ?? 'middle'),
                        'resource_type' => (string) ($node->resource_type ?? ''),
                        'resource_meta' => $resourceMetaList[0] ?? null,
                        'resource_meta_list' => $resourceMetaList,
                        'progress' => [
                            'status' => (string) ($progress?->status ?? DoopLabRoadmapNodeProgress::STATUS_LOCKED),
                            'student_note' => (string) ($progress?->student_note ?? ''),
                            'mentor_note' => (string) ($progress?->mentor_note ?? ''),
                            'submitted_at' => optional($progress?->submitted_at)->toIso8601String(),
                            'reviewed_at' => optional($progress?->reviewed_at)->toIso8601String(),
                        ],
                    ];
                })->values()->all() ?? [],
                'edges' => $enrollment->roadmap?->edges?->filter(fn ($edge) => $edge->fromNode && $edge->toNode)
                    ->map(fn ($edge) => [
                        'uuid' => (string) $edge->uuid,
                        'from_node_uuid' => (string) ($edge->fromNode?->uuid ?? ''),
                        'to_node_uuid' => (string) ($edge->toNode?->uuid ?? ''),
                        'stroke_color' => (string) ($edge->stroke_color ?? '#334155'),
                        'curvature' => (float) ($edge->curvature ?? 0.35),
                    ])
                    ->values()
                    ->all() ?? [],
            ],
        ];

        return Inertia::render('DoopLab/Roadmaps/EnrollmentShow', [
            'enrollment' => $serialized,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isMentor(), 403, 'ROADMAP_ENROLLMENT_MENTOR_ONLY');

        $validated = $request->validate([
            'roadmap_uuid' => ['required', 'string'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'min:1'],
        ]);

        $roadmap = DoopLabRoadmap::query()->where('uuid', $validated['roadmap_uuid'])->firstOrFail();
        abort_unless((int) $roadmap->created_by_user_id === (int) $user->id || $user->isAdmin(), 403, 'ROADMAP_ENROLLMENT_FORBIDDEN');

        $userIds = collect($validated['user_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $existingUsers = User::query()->whereIn('id', $userIds)->pluck('id');

        foreach ($existingUsers as $studentId) {
            $enrollment = DoopLabRoadmapEnrollment::query()->firstOrCreate(
                ['roadmap_id' => $roadmap->id, 'user_id' => (int) $studentId],
                ['mentor_user_id' => $user->id, 'status' => DoopLabRoadmapEnrollment::STATUS_ACTIVE]
            );

            $this->ensureProgressRows($enrollment->fresh(['roadmap.nodes']));
        }

        return redirect()->route('dooplab.roadmaps.index', ['roadmap' => $roadmap->uuid, 'workspace' => 1]);
    }

    public function destroy(Request $request, DoopLabRoadmapEnrollment $enrollment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && ((int) $enrollment->mentor_user_id === (int) $user->id || $user->isAdmin()), 403);

        $roadmap = $enrollment->roadmap;
        $enrollment->delete();

        return redirect()->route('dooplab.roadmaps.index', ['roadmap' => $roadmap?->uuid ?? '', 'workspace' => 1]);
    }

    public function submit(Request $request, DoopLabRoadmapEnrollment $enrollment, string $nodeUuid): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && (int) $enrollment->user_id === (int) $user->id, 403);

        $node = DoopLabRoadmapNode::query()
            ->where('roadmap_id', $enrollment->roadmap_id)
            ->where('uuid', $nodeUuid)
            ->firstOrFail();

        $validated = $request->validate([
            'student_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $progress = DoopLabRoadmapNodeProgress::query()->firstOrCreate(
            ['enrollment_id' => $enrollment->id, 'node_id' => $node->id],
            ['status' => DoopLabRoadmapNodeProgress::STATUS_LOCKED]
        );

        if (! in_array($progress->status, [
            DoopLabRoadmapNodeProgress::STATUS_UNLOCKED,
            DoopLabRoadmapNodeProgress::STATUS_REVISION,
        ], true)) {
            return redirect()->route('dooplab.roadmaps.enrollments.show', $enrollment->uuid);
        }

        $progress->status = DoopLabRoadmapNodeProgress::STATUS_SUBMITTED;
        $progress->student_note = (string) ($validated['student_note'] ?? '');
        $progress->submitted_at = now();
        $progress->save();

        return redirect()->route('dooplab.roadmaps.enrollments.show', $enrollment->uuid);
    }

    public function review(Request $request, DoopLabRoadmapEnrollment $enrollment, string $nodeUuid): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && (int) $enrollment->mentor_user_id === (int) $user->id, 403);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,revision'],
            'mentor_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $node = DoopLabRoadmapNode::query()
            ->where('roadmap_id', $enrollment->roadmap_id)
            ->where('uuid', $nodeUuid)
            ->firstOrFail();

        $progress = DoopLabRoadmapNodeProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('node_id', $node->id)
            ->firstOrFail();

        if ($progress->status !== DoopLabRoadmapNodeProgress::STATUS_SUBMITTED) {
            return redirect()->route('dooplab.roadmaps.enrollments.show', $enrollment->uuid);
        }

        $progress->status = $validated['decision'] === 'approved'
            ? DoopLabRoadmapNodeProgress::STATUS_APPROVED
            : DoopLabRoadmapNodeProgress::STATUS_REVISION;
        $progress->mentor_note = (string) ($validated['mentor_note'] ?? '');
        $progress->reviewed_at = now();
        $progress->save();

        $this->recomputeUnlocks($enrollment->fresh(['roadmap.edges', 'roadmap.nodes']));

        return redirect()->route('dooplab.roadmaps.enrollments.show', $enrollment->uuid);
    }

    public function unlock(Request $request, DoopLabRoadmapEnrollment $enrollment, string $nodeUuid): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && ((int) $enrollment->mentor_user_id === (int) $user->id || $user->isAdmin()), 403);

        $node = DoopLabRoadmapNode::query()
            ->where('roadmap_id', $enrollment->roadmap_id)
            ->where('uuid', $nodeUuid)
            ->firstOrFail();

        $progress = DoopLabRoadmapNodeProgress::query()->firstOrCreate(
            ['enrollment_id' => $enrollment->id, 'node_id' => $node->id],
            ['status' => DoopLabRoadmapNodeProgress::STATUS_LOCKED]
        );

        if ($progress->status === DoopLabRoadmapNodeProgress::STATUS_LOCKED) {
            $progress->status = DoopLabRoadmapNodeProgress::STATUS_UNLOCKED;
            $progress->save();
        }

        return redirect()->route('dooplab.roadmaps.enrollments.show', $enrollment->uuid);
    }

    public function lock(Request $request, DoopLabRoadmapEnrollment $enrollment, string $nodeUuid): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && ((int) $enrollment->mentor_user_id === (int) $user->id || $user->isAdmin()), 403);

        $node = DoopLabRoadmapNode::query()
            ->where('roadmap_id', $enrollment->roadmap_id)
            ->where('uuid', $nodeUuid)
            ->firstOrFail();

        $progress = DoopLabRoadmapNodeProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('node_id', $node->id)
            ->first();

        if ($progress && $progress->status === DoopLabRoadmapNodeProgress::STATUS_UNLOCKED) {
            $progress->status = DoopLabRoadmapNodeProgress::STATUS_LOCKED;
            $progress->save();
        }

        return redirect()->route('dooplab.roadmaps.enrollments.show', $enrollment->uuid);
    }

    private function ensureProgressRows(DoopLabRoadmapEnrollment $enrollment): void
    {
        $existing = DoopLabRoadmapNodeProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->pluck('node_id')
            ->all();

        $missingNodes = $enrollment->roadmap?->nodes?->reject(fn ($node) => in_array((int) $node->id, $existing, true)) ?? collect();
        if ($missingNodes->isEmpty()) {
            return;
        }

        $rows = $missingNodes->map(fn ($node) => [
            'enrollment_id' => $enrollment->id,
            'node_id' => (int) $node->id,
            'status' => DoopLabRoadmapNodeProgress::STATUS_LOCKED,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('dooplab_roadmap_node_progress')->insert($rows);
    }

    private function recomputeUnlocks(DoopLabRoadmapEnrollment $enrollment): void
    {
        $progressItems = DoopLabRoadmapNodeProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->get()
            ->keyBy('node_id');

        $nodes = $enrollment->roadmap?->nodes ?? collect();
        $edges = $enrollment->roadmap?->edges ?? collect();

        $incoming = [];
        foreach ($edges as $edge) {
            $incoming[(int) $edge->to_node_id][] = (int) $edge->from_node_id;
        }

        foreach ($nodes as $node) {
            $progress = $progressItems->get((int) $node->id);
            if (! $progress) continue;
            if (in_array($progress->status, [
                DoopLabRoadmapNodeProgress::STATUS_SUBMITTED,
                DoopLabRoadmapNodeProgress::STATUS_APPROVED,
                DoopLabRoadmapNodeProgress::STATUS_REVISION,
            ], true)) {
                continue;
            }

            $parents = $incoming[(int) $node->id] ?? [];
            $shouldUnlock = empty($parents);
            if (! $shouldUnlock) {
                $shouldUnlock = collect($parents)->every(function ($parentId) use ($progressItems) {
                    $parentProgress = $progressItems->get((int) $parentId);
                    return $parentProgress && $parentProgress->status === DoopLabRoadmapNodeProgress::STATUS_APPROVED;
                });
            }

            $nextStatus = $shouldUnlock
                ? DoopLabRoadmapNodeProgress::STATUS_UNLOCKED
                : DoopLabRoadmapNodeProgress::STATUS_LOCKED;

            if ($progress->status !== $nextStatus) {
                $progress->status = $nextStatus;
                $progress->save();
            }
        }
    }

    private function resolveResourceMetaList(DoopLabRoadmapNode $node): array
    {
        $result = [];
        foreach ($node->resources as $resource) {
            if ($resource->resource_type === 'guide') {
                $guide = Guide::query()->find($resource->resource_id);
                if ($guide) {
                    $result[] = [
                        'type' => 'guide',
                        'label' => (string) $guide->title,
                        'href' => route('guides.user.show', $guide->uuid),
                    ];
                }
            }

            if ($resource->resource_type === 'quest') {
                $quest = Quest::query()->find($resource->resource_id);
                if ($quest) {
                    $result[] = [
                        'type' => 'quest',
                        'label' => (string) $quest->title,
                        'href' => route('quests.show', $quest->uuid),
                    ];
                }
            }
        }

        return $result;
    }
}
