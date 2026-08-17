<?php

namespace App\Http\Controllers;

use App\Models\DoopLabRoadmap;
use App\Models\DoopLabRoadmapEdge;
use App\Models\DoopLabRoadmapEnrollment;
use App\Models\DoopLabRoadmapNode;
use App\Models\DoopLabRoadmapNodeProgress;
use App\Models\Guide;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DoopLabRoadmapEnrollmentController extends Controller
{
    public function management(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole(User::ROLE_SUPER_ADMIN), 403, 'ROADMAP_MANAGEMENT_SUPER_ADMIN_ONLY');

        $roadmaps = DoopLabRoadmap::query()
            ->orderBy('title')
            ->get(['id', 'uuid', 'title', 'is_published'])
            ->map(fn (DoopLabRoadmap $roadmap) => [
                'uuid' => (string) $roadmap->uuid,
                'title' => (string) $roadmap->title,
                'is_published' => (bool) $roadmap->is_published,
            ])
            ->values()
            ->all();

        $members = User::query()
            ->whereIn('role', [User::ROLE_STUDENT, User::ROLE_USER])
            ->whereHas('inventories', fn ($query) => $query
                ->where('quantity', '>=', 1)
                ->whereHas('item', fn ($itemQuery) => $itemQuery->where('code', 'dooplab_key')))
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'role'])
            ->map(fn (User $member) => [
                'id' => (int) $member->id,
                'name' => (string) ($member->name ?? ''),
                'username' => (string) ($member->username ?? ''),
                'email' => (string) ($member->email ?? ''),
                'role' => (string) ($member->role ?? ''),
            ])
            ->values()
            ->all();

        $mentors = User::query()
            ->whereIn('role', [User::ROLE_MENTOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'role'])
            ->map(fn (User $mentor) => [
                'id' => (int) $mentor->id,
                'name' => (string) ($mentor->name ?? ''),
                'username' => (string) ($mentor->username ?? ''),
                'role' => (string) ($mentor->role ?? ''),
            ])
            ->values()
            ->all();

        $enrollments = DoopLabRoadmapEnrollment::query()
            ->with([
                'roadmap:id,uuid,title,is_published',
                'user:id,name,username,email,role',
                'mentor:id,name,username,role',
            ])
            ->latest('updated_at')
            ->get()
            ->map(fn (DoopLabRoadmapEnrollment $enrollment) => [
                'uuid' => (string) $enrollment->uuid,
                'status' => (string) $enrollment->status,
                'review_mode' => (string) ($enrollment->review_mode ?? DoopLabRoadmapEnrollment::REVIEW_MODE_MANUAL),
                'updated_at' => $enrollment->updated_at?->toIso8601String(),
                'roadmap' => [
                    'uuid' => (string) ($enrollment->roadmap?->uuid ?? ''),
                    'title' => (string) ($enrollment->roadmap?->title ?? ''),
                    'is_published' => (bool) ($enrollment->roadmap?->is_published ?? false),
                ],
                'member' => [
                    'id' => (int) ($enrollment->user?->id ?? 0),
                    'name' => (string) ($enrollment->user?->name ?? ''),
                    'username' => (string) ($enrollment->user?->username ?? ''),
                    'email' => (string) ($enrollment->user?->email ?? ''),
                ],
                'mentor' => [
                    'id' => (int) ($enrollment->mentor?->id ?? 0),
                    'name' => (string) ($enrollment->mentor?->name ?? ''),
                    'username' => (string) ($enrollment->mentor?->username ?? ''),
                ],
            ])
            ->values()
            ->all();

        return Inertia::render('DoopLab/Roadmaps/Management', compact('roadmaps', 'members', 'mentors', 'enrollments'));
    }

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
                'review_mode' => (string) ($enrollment->review_mode ?? DoopLabRoadmapEnrollment::REVIEW_MODE_MANUAL),
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
        $canManage = $isMentor || $user->isAdmin();
        abort_unless($isOwner || $canManage, 403, 'ROADMAP_ENROLLMENT_FORBIDDEN');

        $enrollment->load([
            'roadmap.sections',
            'roadmap.nodes.resources',
            'roadmap.textBlocks',
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
            'review_mode' => (string) ($enrollment->review_mode ?? DoopLabRoadmapEnrollment::REVIEW_MODE_MANUAL),
            'is_owner' => $isOwner,
            'is_mentor' => $isMentor,
            'can_manage' => $canManage,
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
                'nodes' => $enrollment->roadmap?->nodes?->map(function ($node) use ($progressMap, $enrollment) {
                    $progress = $progressMap->get((int) $node->id);
                    $resourceMetaList = $this->resolveResourceMetaList($node, $enrollment);

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
                'text_blocks' => $enrollment->roadmap?->textBlocks?->map(fn ($textBlock) => [
                    'uuid' => (string) $textBlock->uuid,
                    'content' => (string) ($textBlock->content ?? ''),
                    'x' => (int) $textBlock->x,
                    'y' => (int) $textBlock->y,
                    'width' => (int) $textBlock->width,
                    'height' => (int) $textBlock->height,
                    'bg_color' => (string) ($textBlock->bg_color ?? 'transparent'),
                    'text_color' => (string) ($textBlock->text_color ?? '#e6f6ff'),
                    'font_size' => (int) ($textBlock->font_size ?? 16),
                    'text_align' => (string) ($textBlock->text_align ?? 'left'),
                    'text_valign' => (string) ($textBlock->text_valign ?? 'top'),
                ])->values()->all() ?? [],
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
        abort_unless($user && ($user->isMentor() || $user->isAdmin()), 403, 'ROADMAP_ENROLLMENT_MENTOR_ONLY');

        $validated = $request->validate([
            'roadmap_uuid' => ['required', 'string'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'min:1'],
            'review_mode' => ['nullable', 'string', 'in:manual,auto'],
        ]);

        $roadmap = DoopLabRoadmap::query()->where('uuid', $validated['roadmap_uuid'])->firstOrFail();
        abort_unless((int) $roadmap->created_by_user_id === (int) $user->id || $user->isAdmin(), 403, 'ROADMAP_ENROLLMENT_FORBIDDEN');

        $userIds = collect($validated['user_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $existingUsers = User::query()->whereIn('id', $userIds)->pluck('id');
        $reviewMode = (string) ($validated['review_mode'] ?? DoopLabRoadmapEnrollment::REVIEW_MODE_MANUAL);

        foreach ($existingUsers as $studentId) {
            $enrollment = DoopLabRoadmapEnrollment::query()->firstOrNew([
                'roadmap_id' => $roadmap->id,
                'user_id' => (int) $studentId,
            ]);

            if (! $enrollment->exists) {
                $enrollment->mentor_user_id = (int) $user->id;
                $enrollment->status = DoopLabRoadmapEnrollment::STATUS_ACTIVE;
            }

            $enrollment->review_mode = $reviewMode;
            $enrollment->save();

            $this->ensureProgressRows($enrollment->fresh(['roadmap.nodes']));
        }

        return redirect()->route('dooplab.roadmaps.index');
    }

    public function destroy(Request $request, DoopLabRoadmapEnrollment $enrollment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && ((int) $enrollment->mentor_user_id === (int) $user->id || $user->isAdmin()), 403);

        $enrollment->delete();

        return back()->with('message', 'ROADMAP_MEMBER_REMOVED');
    }

    public function updateAssignment(Request $request, DoopLabRoadmapEnrollment $enrollment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole(User::ROLE_SUPER_ADMIN), 403, 'ROADMAP_MANAGEMENT_SUPER_ADMIN_ONLY');

        $validated = $request->validate([
            'roadmap_uuid' => ['required', 'string', 'exists:dooplab_roadmaps,uuid'],
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
            'mentor_user_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:active,ended'],
            'review_mode' => ['required', 'string', 'in:manual,auto'],
        ]);

        $roadmap = DoopLabRoadmap::query()
            ->where('uuid', (string) $validated['roadmap_uuid'])
            ->firstOrFail();

        $student = User::query()
            ->whereKey((int) $validated['student_user_id'])
            ->whereIn('role', [User::ROLE_STUDENT, User::ROLE_USER])
            ->firstOrFail();

        $mentor = User::query()
            ->whereKey((int) $validated['mentor_user_id'])
            ->whereIn('role', [User::ROLE_MENTOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->firstOrFail();

        $duplicateExists = DoopLabRoadmapEnrollment::query()
            ->where('roadmap_id', (int) $roadmap->id)
            ->where('user_id', (int) $student->id)
            ->whereKeyNot((int) $enrollment->id)
            ->exists();

        if ($duplicateExists) {
            return back()->withErrors([
                'student_user_id' => 'Student ini sudah punya enrollment untuk roadmap tersebut.',
            ]);
        }

        $roadmapChanged = (int) $enrollment->roadmap_id !== (int) $roadmap->id;

        DB::transaction(function () use ($enrollment, $roadmap, $student, $mentor, $validated, $roadmapChanged): void {
            $enrollment->update([
                'roadmap_id' => (int) $roadmap->id,
                'user_id' => (int) $student->id,
                'mentor_user_id' => (int) $mentor->id,
                'status' => (string) $validated['status'],
                'review_mode' => (string) $validated['review_mode'],
            ]);

            if ($roadmapChanged) {
                $enrollment->nodeProgress()->delete();
            }
        });

        $this->ensureProgressRows($enrollment->fresh(['roadmap.nodes']));

        return back()->with('message', 'ROADMAP_ASSIGNMENT_UPDATED');
    }

    public function storeAssignments(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole(User::ROLE_SUPER_ADMIN), 403, 'ROADMAP_MANAGEMENT_SUPER_ADMIN_ONLY');

        $validated = $request->validate([
            'roadmap_uuid' => ['required', 'string', 'exists:dooplab_roadmaps,uuid'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'mentor_user_id' => ['required', 'integer', 'exists:users,id'],
            'review_mode' => ['required', 'string', 'in:manual,auto'],
        ]);

        $roadmap = DoopLabRoadmap::query()->where('uuid', (string) $validated['roadmap_uuid'])->firstOrFail();
        $mentor = User::query()
            ->whereKey((int) $validated['mentor_user_id'])
            ->whereIn('role', [User::ROLE_MENTOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->firstOrFail();
        $students = User::query()
            ->whereIn('id', collect($validated['user_ids'])->map(fn ($id) => (int) $id)->unique())
            ->whereIn('role', [User::ROLE_STUDENT, User::ROLE_USER])
            ->whereHas('inventories', fn ($query) => $query
                ->where('quantity', '>=', 1)
                ->whereHas('item', fn ($itemQuery) => $itemQuery->where('code', 'dooplab_key')))
            ->get();

        if ($students->count() !== collect($validated['user_ids'])->map(fn ($id) => (int) $id)->unique()->count()) {
            return back()->withErrors([
                'user_ids' => 'Semua member yang dipilih wajib memiliki DoopLab ID Card.',
            ]);
        }

        DB::transaction(function () use ($students, $roadmap, $mentor, $validated): void {
            foreach ($students as $student) {
                $enrollment = DoopLabRoadmapEnrollment::query()->firstOrNew([
                    'roadmap_id' => (int) $roadmap->id,
                    'user_id' => (int) $student->id,
                ]);

                $enrollment->mentor_user_id = (int) $mentor->id;
                $enrollment->status = DoopLabRoadmapEnrollment::STATUS_ACTIVE;
                $enrollment->review_mode = (string) $validated['review_mode'];
                $enrollment->save();

                $this->ensureProgressRows($enrollment->fresh(['roadmap.nodes']));
            }
        });

        return back()->with('message', 'ROADMAP_MEMBERS_ADDED');
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

        $progress->student_note = (string) ($validated['student_note'] ?? '');
        $progress->mentor_override_status = null;
        $progress->submitted_at = now();

        if ($enrollment->isAutoReview()) {
            $progress->status = DoopLabRoadmapNodeProgress::STATUS_APPROVED;
            $progress->reviewed_at = now();
        } else {
            $progress->status = DoopLabRoadmapNodeProgress::STATUS_SUBMITTED;
        }

        $progress->save();

        if ($enrollment->isAutoReview()) {
            $this->recomputeUnlocks($enrollment->fresh(['roadmap.edges', 'roadmap.nodes']));
        }

        return redirect()->route('dooplab.roadmaps.enrollments.show', $enrollment->uuid);
    }

    public function review(Request $request, DoopLabRoadmapEnrollment $enrollment, string $nodeUuid): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && ((int) $enrollment->mentor_user_id === (int) $user->id || $user->isAdmin()), 403);

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
        $progress->mentor_override_status = null;
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

        $progress->status = DoopLabRoadmapNodeProgress::STATUS_UNLOCKED;
        $progress->mentor_override_status = DoopLabRoadmapNodeProgress::STATUS_UNLOCKED;
        $progress->save();

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

        if ($progress) {
            $progress->status = DoopLabRoadmapNodeProgress::STATUS_LOCKED;
            $progress->mentor_override_status = DoopLabRoadmapNodeProgress::STATUS_LOCKED;
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

            if (in_array($progress->mentor_override_status, [
                DoopLabRoadmapNodeProgress::STATUS_LOCKED,
                DoopLabRoadmapNodeProgress::STATUS_UNLOCKED,
            ], true)) {
                if ($progress->status !== $progress->mentor_override_status) {
                    $progress->status = $progress->mentor_override_status;
                    $progress->save();
                }
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

    private function resolveResourceMetaList(DoopLabRoadmapNode $node, DoopLabRoadmapEnrollment $enrollment): array
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
                        'submission_inspect_href' => null,
                    ];
                }
            }

            if ($resource->resource_type === 'quest') {
                $quest = Quest::query()->find($resource->resource_id);
                if ($quest) {
                    $submission = Submission::where('quest_id', $quest->id)
                        ->where('user_id', $enrollment->user_id)
                        ->latest()
                        ->first();

                    $result[] = [
                        'type' => 'quest',
                        'label' => (string) $quest->title,
                        'href' => route('quests.show', $quest->uuid),
                        'submission_inspect_href' => $submission
                            ? route('admin.submissions.inspect', $submission->uuid)
                            : null,
                    ];
                }
            }
        }

        return $result;
    }
}
