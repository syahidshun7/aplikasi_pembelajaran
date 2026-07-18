<?php
namespace App\Http\Controllers;

use App\Events\JoinGroupRequested;
use App\Models\DoopLabRoadmap;
use App\Models\DoopLabRoadmapEdge;
use App\Models\DoopLabRoadmapNode;
use App\Models\DoopLabRoadmapSection;
use App\Models\DoopLabRoadmapTextBlock;
use App\Models\Guide;
use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\StudyGroupJoinRequest;
use App\Models\User;
use App\Services\LevelingService;
use App\Services\StudyGroupAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class StudyGroupController extends Controller
{
    public function __construct(private readonly StudyGroupAccessService $groupAccessService)
    {
    }
    // Lihat daftar semua party
    public function index(Request $request)
    {
        $user = Auth::user();
        $userId = (int) Auth::id();
        $userJobId = $user?->job_id;
        $canManageMembership = $this->canManageStudentMembership($user);
        $search = trim((string) $request->input('search', ''));

        $userGroupIds = $user && $canManageMembership
            ? $user->studyGroups()
                ->where('study_groups.job_id', $userJobId)
                ->pluck('study_groups.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $groupRequestStatuses = $userId > 0 && $canManageMembership
            ? StudyGroupJoinRequest::query()
                ->where('user_id', $userId)
                ->pluck('status', 'study_group_id')
                ->toArray()
            : [];

        $query = StudyGroup::query()
            ->with('job:id,name')
            ->withCount([
                'users as users_count' => fn ($userQuery) => $userQuery->whereNotIn('users.role', User::staffRoles()),
            ])
            ->withCount([
                'joinRequests as pending_requests_count' => fn ($joinRequestQuery) => $joinRequestQuery->where('status', 'pending'),
            ])
            ->where('job_id', $userJobId);

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $groups = $query
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $viewerHasLevelGatePass = $this->groupAccessService->hasPaidLevelGatePass($user);

        $groups->getCollection()->transform(function (StudyGroup $group) use ($userGroupIds, $groupRequestStatuses, $user, $viewerHasLevelGatePass) {
            $payload = $group->toArray();
            $groupId = (int) $group->id;
            $payload['is_member'] = in_array($groupId, $userGroupIds, true);
            $payload['join_request_status'] = $groupRequestStatuses[$groupId] ?? null;
            $payload['min_level'] = (int) ($group->min_level ?? 1);
            $payload['has_level_gate_pass'] = $viewerHasLevelGatePass || $this->groupAccessService->hasPaidLevelGatePass($user, $group);
            return $payload;
        });

        $userLevel = $user
            ? LevelingService::levelFromExp((int) ($user->exp ?? 0))
            : 1;

        return Inertia::render('StudyGroups/Index', [
            'groups' => $groups,
            'filters' => [
                'search' => $search,
            ],
            'viewerLevel' => $userLevel,
            'viewerHasLevelGatePass' => $viewerHasLevelGatePass,
        ]);
    }

    public function show(Request $request, string $uuid)
    {
        $user = $request->user();

        $group = StudyGroup::query()
            ->with('job:id,name')
            ->withCount([
                'users as members_count' => fn ($userQuery) => $userQuery->whereNotIn('users.role', User::staffRoles()),
                'quests as quests_count',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $isMember = $group->users()
            ->where('users.id', (int) $user->id)
            ->exists();

        abort_unless($isMember, 403, 'GROUP_DETAIL_FOR_MEMBERS_ONLY');

        $memberRows = $group->users()
            ->select('users.id', 'users.name', 'users.username', 'users.role', 'users.profile_photo', 'users.job_id')
            ->with('job:id,name')
            ->orderBy('users.name')
            ->get();

        $mentors = $memberRows
            ->filter(function (User $member) {
                $pivotRole = strtolower((string) ($member->pivot?->role ?? ''));

                return $member->isMentor()
                    || in_array($pivotRole, ['mentor', 'mentor_observer'], true);
            })
            ->map(fn (User $mentor) => [
                'id' => (int) $mentor->id,
                'name' => (string) $mentor->name,
                'username' => (string) ($mentor->username ?? ''),
                'role' => (string) ($mentor->pivot?->role ?? $mentor->role),
                'job_name' => (string) ($mentor->job?->name ?? $group->job?->name ?? 'Mentor'),
                'profile_photo' => $mentor->profile_photo,
            ])
            ->values();

        $classmates = $memberRows
            ->reject(function (User $member) {
                $pivotRole = strtolower((string) ($member->pivot?->role ?? ''));

                return $member->isMentor()
                    || in_array($pivotRole, ['mentor', 'mentor_observer'], true);
            })
            ->map(fn (User $member) => [
                'id' => (int) $member->id,
                'name' => (string) $member->name,
                'username' => (string) ($member->username ?? ''),
                'profile_photo' => $member->profile_photo,
            ])
            ->values();

        $classRoadmaps = $group->roadmaps()
            ->wherePivot('is_active', true)
            ->where('is_published', true)
            ->with([
                'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'nodes.resources',
                'textBlocks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'edges.fromNode:id,uuid',
                'edges.toNode:id,uuid',
            ])
            ->get()
            ->map(fn (DoopLabRoadmap $roadmap) => $this->serializeClassRoadmap($roadmap))
            ->values();

        return Inertia::render('StudyGroups/Show', [
            'group' => [
                'uuid' => (string) $group->uuid,
                'name' => (string) $group->name,
                'description' => (string) ($group->description ?? ''),
                'max_members' => (int) ($group->max_members ?? 0),
                'min_level' => (int) ($group->min_level ?? 1),
                'members_count' => (int) ($group->members_count ?? 0),
                'quests_count' => (int) ($group->quests_count ?? 0),
                'job' => $group->job ? [
                    'id' => (int) $group->job->id,
                    'name' => (string) $group->job->name,
                ] : null,
            ],
            'mentors' => $mentors,
            'classmates' => $classmates,
            'classRoadmaps' => $classRoadmaps,
        ]);
    }

    // Logic JOIN Party
    public function join(Request $request)
    {
        if (! $this->canManageStudentMembership($request->user())) {
            return back()->withErrors([
                'study_group_uuid' => 'Staff play mode admin tidak bisa join kelas student.',
            ]);
        }

        $validated = $request->validate([
            'study_group_uuid' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:10', 'max:500', 'regex:/\S/'],
        ], [
            'reason.required' => 'Alasan bergabung wajib diisi.',
            'reason.min' => 'Alasan bergabung minimal 10 karakter.',
            'reason.max' => 'Alasan bergabung maksimal 500 karakter.',
            'reason.regex' => 'Alasan bergabung tidak boleh kosong.',
        ]);
        $groupUuid = trim((string) $validated['study_group_uuid']);
        $joinReason = trim((string) ($validated['reason'] ?? ''));
        $userId = (int) Auth::id();

        $group = StudyGroup::query()
            ->where('uuid', $groupUuid)
            ->first();
        if (! $group) {
            return back()->withErrors(['study_group_uuid' => 'GROUP_NOT_FOUND: Party tidak ditemukan.']);
        }

        $user = Auth::user();
        if ((int) $group->job_id !== (int) ($user->job_id ?? 0)) {
            // Jangan bocorkan detail mismatch jobs, tampilkan seperti group tidak ditemukan.
            return back()->withErrors(['study_group_uuid' => 'GROUP_NOT_FOUND: Party tidak ditemukan.']);
        }

        $userLevel = LevelingService::levelFromExp((int) ($user->exp ?? 0));
        $minLevel = (int) ($group->min_level ?? 1);
        $hasLevelGatePass = $this->groupAccessService->hasPaidLevelGatePass($user, $group);

        if ($userLevel < $minLevel && ! $hasLevelGatePass) {
            return back()->withErrors([
                'study_group_uuid' => "LEVEL_TOO_LOW: Kamu butuh minimal Level {$minLevel} untuk join party ini. Level kamu saat ini: {$userLevel}.",
            ]);
        }

        if ($group->users()->where('user_id', $userId)->exists()) {
            return back()->withErrors(['study_group_uuid' => 'ALREADY_MEMBER: Kamu sudah di dalam party ini.']);
        }

        $joinRequest = StudyGroupJoinRequest::firstOrNew([
            'study_group_id' => $group->id,
            'user_id' => $userId,
        ]);

        if ($joinRequest->exists && $joinRequest->status === 'pending') {
            return back()->withErrors(['study_group_uuid' => 'REQUEST_PENDING: Menunggu persetujuan admin group.']);
        }

        $joinRequest->status = 'pending';
        $joinRequest->reason = $joinReason;
        $joinRequest->processed_by = null;
        $joinRequest->save();
        JoinGroupRequested::dispatch(
            $joinRequest->loadMissing([
                'user:id,name,username,email',
                'studyGroup:id,uuid,name',
            ])
        );

        return back()->with('message', 'JOIN_REQUEST_SENT_WAITING_APPROVAL');
    }

    // Logic LEAVE Party
    public function leave($uuid)
    {
        if (! $this->canManageStudentMembership(Auth::user())) {
            return back()->withErrors([
                'study_group_uuid' => 'Staff play mode admin tidak memakai membership kelas student.',
            ]);
        }

        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $group->softRemoveMember((int) Auth::id());

        return back()->with('message', 'LEFT_THE_PARTY');
    }

    private function canManageStudentMembership(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! $user->isStaffPlayMode()) {
            return true;
        }

        return $user->isMentor();
    }

    private function serializeClassRoadmap(DoopLabRoadmap $roadmap): array
    {
        return [
            'uuid' => (string) $roadmap->uuid,
            'title' => (string) ($roadmap->title ?? ''),
            'description' => (string) ($roadmap->description ?? ''),
            'sections' => $roadmap->sections
                ->map(fn (DoopLabRoadmapSection $section) => [
                    'uuid' => (string) $section->uuid,
                    'title' => (string) ($section->title ?? ''),
                    'x' => (int) $section->x,
                    'y' => (int) $section->y,
                    'width' => (int) $section->width,
                    'height' => (int) $section->height,
                    'bg_color' => (string) ($section->bg_color ?? '#dbeafe'),
                    'text_color' => (string) ($section->text_color ?? '#1e3a8a'),
                    'font_size' => (int) ($section->font_size ?? 20),
                    'text_align' => (string) ($section->text_align ?? 'left'),
                    'text_valign' => (string) ($section->text_valign ?? 'top'),
                ])
                ->values()
                ->all(),
            'nodes' => $roadmap->nodes
                ->map(fn (DoopLabRoadmapNode $node) => [
                    'uuid' => (string) $node->uuid,
                    'title' => (string) ($node->title ?? ''),
                    'x' => (int) $node->x,
                    'y' => (int) $node->y,
                    'width' => (int) $node->width,
                    'height' => (int) $node->height,
                    'bg_color' => (string) ($node->bg_color ?? '#93c5fd'),
                    'text_color' => (string) ($node->text_color ?? '#0f172a'),
                    'font_size' => (int) ($node->font_size ?? 28),
                    'text_align' => (string) ($node->text_align ?? 'center'),
                    'text_valign' => (string) ($node->text_valign ?? 'middle'),
                    'resource_meta_list' => $this->resolveClassRoadmapResourceMetaList($node),
                ])
                ->values()
                ->all(),
            'text_blocks' => $roadmap->textBlocks
                ->map(fn (DoopLabRoadmapTextBlock $textBlock) => [
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
                ])
                ->values()
                ->all(),
            'edges' => $roadmap->edges
                ->filter(fn (DoopLabRoadmapEdge $edge) => $edge->fromNode && $edge->toNode)
                ->map(fn (DoopLabRoadmapEdge $edge) => [
                    'uuid' => (string) $edge->uuid,
                    'from_node_uuid' => (string) ($edge->fromNode?->uuid ?? ''),
                    'to_node_uuid' => (string) ($edge->toNode?->uuid ?? ''),
                    'stroke_color' => (string) ($edge->stroke_color ?? '#334155'),
                    'curvature' => (float) ($edge->curvature ?? 0.35),
                ])
                ->values()
                ->all(),
        ];
    }

    private function resolveClassRoadmapResourceMetaList(DoopLabRoadmapNode $node): array
    {
        $items = $node->resources->map(fn ($resource) => [
            'type' => (string) $resource->resource_type,
            'id' => (int) $resource->resource_id,
        ])->values()->all();

        if ($items === [] && $node->resource_type && $node->resource_id) {
            $items[] = [
                'type' => (string) $node->resource_type,
                'id' => (int) $node->resource_id,
            ];
        }

        return collect($items)
            ->map(function (array $item) {
                if ($item['type'] === 'guide') {
                    $guide = Guide::query()->find((int) $item['id']);

                    return $guide ? [
                        'type' => 'guide',
                        'label' => (string) $guide->title,
                        'href' => route('guides.user.show', $guide->uuid),
                    ] : null;
                }

                if ($item['type'] === 'quest') {
                    $quest = Quest::query()->find((int) $item['id']);

                    return $quest ? [
                        'type' => 'quest',
                        'label' => (string) $quest->title,
                        'href' => route('quests.show', $quest->uuid),
                    ] : null;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
