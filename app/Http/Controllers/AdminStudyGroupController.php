<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\JobRole;
use App\Models\DoopLabRoadmap;
use App\Models\StudyGroup;
use App\Models\StudyGroupJoinRequest;
use App\Notifications\JoinGroupRequestRejectedNotification;
use App\Models\User;
use App\Services\LevelingService;
use App\Services\StudyGroupStaffAccessService;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;

class AdminStudyGroupController extends Controller
{
     public function manage(Request $request, StudyGroupStaffAccessService $access)
{
    $validated = $request->validate([
        'search' => ['nullable', 'string', 'max:255'],
        'view' => ['nullable', 'in:active,trash'],
    ]);
    $search = trim((string) ($validated['search'] ?? ''));
    $view = (string) ($validated['view'] ?? 'active');

    return Inertia::render('StudyGroups/Admin/Index', [
        'groups' => StudyGroup::query()
            ->tap(fn ($query) => $access->scopeAccessibleGroups($query, auth()->user()))
            ->when($view === 'trash', fn ($query) => $query->onlyTrashed())
            ->with('job:id,name')
            ->withCount('staff as staff_count')
            ->withCount([
                'users as users_count' => fn ($userQuery) => $userQuery->whereNotIn('users.role', User::staffRoles()),
                'joinRequests as pending_requests_count' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('invite_code', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString(),
        'filters' => [
            'search' => $search,
            'view' => $view,
        ],
        'jobs' => JobRole::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
    ]);
}
     
    public function store(Request $request)
    {
        // Pastikan hanya admin (sesuaikan dengan logic middleware/role kamu)
        if (!Auth::user()->isAdmin()) {
            abort(403, 'UNAUTHORIZED_ACCESS');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_members' => 'required|integer|min:1|max:50',
            'min_level' => 'required|integer|min:1|max:100',
            'job_id' => 'required|exists:job_roles,id',
        ]);

        // Karena Model StudyGroup sudah pakai HasUuids, 
        // kita tidak perlu menulis 'uuid' => Str::uuid() di sini.
        StudyGroup::create([
            'name' => $request->name,
            'description' => $request->description,
            'max_members' => $request->max_members,
            'min_level' => (int) $request->min_level,
            'job_id' => (int) $request->job_id,
            'invite_code' => $this->generateUniqueInviteCode(),
        ]);

        CacheVersion::bump('study_groups');

        return back()->with('message', 'NEW_PARTY_ESTABLISHED');
    }


    public function update(Request $request, $uuid)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'ADMIN_ONLY_STUDY_GROUP_MUTATION');

        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $oldJobId = (int) ($group->job_id ?? 0);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_members' => 'required|integer|min:1|max:50',
            'min_level' => 'required|integer|min:1|max:100',
            'job_id' => 'required|exists:job_roles,id',
        ]);

        $newJobId = (int) $request->job_id;

        if ($newJobId !== $oldJobId) {
            $memberIds = $group->users()->pluck('users.id');

            if ($memberIds->isNotEmpty()) {
                $hasConflicts = StudyGroup::query()
                    ->where('id', '!=', $group->id)
                    ->whereIn('id', function ($q) use ($memberIds) {
                        $q->from('group_user')
                            ->select('study_group_id')
                            ->whereIn('user_id', $memberIds)
                            ->whereNull('deleted_at');
                    })
                    ->where('job_id', '!=', $newJobId)
                    ->exists();

                if ($hasConflicts) {
                    return back()->withErrors([
                        'job_id' => 'JOB_CONFLICT: Some current members belong to groups with different jobs.',
                    ]);
                }
            }
        }

        $group->update([
            'name' => $request->name,
            'description' => $request->description,
            'max_members' => $request->max_members,
            'min_level' => (int) $request->min_level,
            'job_id' => $newJobId,
        ]);

        CacheVersion::bump('study_groups');

        if ($newJobId !== $oldJobId) {
            User::query()
                ->whereIn('id', function ($q) use ($group) {
                    $q->from('group_user')
                        ->select('user_id')
                        ->where('study_group_id', $group->id)
                        ->whereNull('deleted_at');
                })
                ->update(['job_id' => $newJobId]);
        }

        return back()->with('message', 'DATA_UPDATED_SUCCESSFULLY');
    }

    public function detail($uuid, StudyGroupStaffAccessService $access)
    {
        $group = StudyGroup::query()
            ->with([
                'staff' => fn ($query) => $query->select('users.id', 'users.name', 'users.username', 'users.email', 'users.role')->orderBy('users.name'),
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $access->canAccess(auth()->user(), $group)) {
            abort(403, 'STUDY_GROUP_STAFF_ACCESS_DENIED');
        }

        $questCounts = \App\Models\Quest::query()
            ->where('study_group_id', $group->id)
            ->selectRaw("COUNT(*) as total, SUM(quest_type = 'main') as main_count, SUM(quest_type = 'optional') as optional_count")
            ->first();

        return Inertia::render('StudyGroups/Admin/Detail', [
            'group' => $group,
            'questCounts' => [
                'total' => (int) ($questCounts->total ?? 0),
                'main' => (int) ($questCounts->main_count ?? 0),
                'optional' => (int) ($questCounts->optional_count ?? 0),
            ],
            'studentDashboard' => $this->buildStudentDashboard($group),
            'staffMembers' => $group->staff
                ->map(fn (User $staff) => [
                    'id' => (int) $staff->id,
                    'name' => (string) $staff->name,
                    'username' => (string) ($staff->username ?? ''),
                    'email' => (string) $staff->email,
                    'role' => (string) $staff->role,
                    'role_in_group' => (string) ($staff->pivot?->role_in_group ?? 'mentor'),
                ])
                ->values(),
            'availableStaff' => auth()->user()?->isAdmin()
                ? User::query()
                    ->whereIn('role', User::staffRoles())
                    ->where('id', '!=', auth()->id())
                    ->orderBy('name')
                    ->get(['id', 'name', 'username', 'email', 'role'])
                    ->map(fn (User $staff) => [
                        'id' => (int) $staff->id,
                        'name' => (string) $staff->name,
                        'username' => (string) ($staff->username ?? ''),
                        'email' => (string) $staff->email,
                        'role' => (string) $staff->role,
                    ])
                    ->values()
                : [],
            'canManageStaffAccess' => (bool) auth()->user()?->isAdmin(),
        ]);
    }

    public function roadmaps($uuid, StudyGroupStaffAccessService $access): Response
    {
        $group = StudyGroup::query()
            ->with([
                'roadmaps' => fn ($query) => $query
                    ->orderBy('study_group_roadmaps.sort_order')
                    ->orderBy('study_group_roadmaps.id'),
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $access->canAccess(auth()->user(), $group)) {
            abort(403, 'STUDY_GROUP_STAFF_ACCESS_DENIED');
        }

        return Inertia::render('StudyGroups/Admin/Roadmaps', [
            'group' => $group,
            'attachedRoadmaps' => $group->roadmaps
                ->map(fn (DoopLabRoadmap $roadmap) => [
                    'uuid' => (string) $roadmap->uuid,
                    'title' => (string) ($roadmap->title ?? ''),
                    'description' => (string) ($roadmap->description ?? ''),
                    'is_published' => (bool) $roadmap->is_published,
                    'sort_order' => (int) ($roadmap->pivot?->sort_order ?? 0),
                    'is_active' => (bool) ($roadmap->pivot?->is_active ?? true),
                ])
                ->values(),
            'availableRoadmaps' => DoopLabRoadmap::query()
                ->where('is_published', true)
                ->orderBy('title')
                ->get(['id', 'uuid', 'title', 'description'])
                ->map(fn (DoopLabRoadmap $roadmap) => [
                    'uuid' => (string) $roadmap->uuid,
                    'title' => (string) ($roadmap->title ?? ''),
                    'description' => (string) ($roadmap->description ?? ''),
                ])
                ->values(),
        ]);
    }

    public function attendanceDashboard($uuid, StudyGroupStaffAccessService $access): Response
    {
        $group = StudyGroup::query()->where('uuid', $uuid)->firstOrFail();

        if (! $access->canAccess(auth()->user(), $group)) {
            abort(403, 'STUDY_GROUP_STAFF_ACCESS_DENIED');
        }

        return Inertia::render('StudyGroups/Admin/AttendanceDashboard', [
            'group' => $group,
            'attendanceDashboard' => $this->buildAttendanceDashboard($group),
        ]);
    }

    public function joinRequests($uuid, StudyGroupStaffAccessService $access): Response
    {
        $group = StudyGroup::query()->where('uuid', $uuid)->firstOrFail();

        if (! $access->canAccess(auth()->user(), $group)) {
            abort(403, 'STUDY_GROUP_STAFF_ACCESS_DENIED');
        }

        $requests = $group->joinRequests()
            ->with('user:id,name,username,email,exp')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function ($requestItem) {
                $requestItem->user_level = LevelingService::levelFromExp((int) ($requestItem->user?->exp ?? 0));

                return $requestItem;
            })
            ->values();

        return Inertia::render('StudyGroups/Admin/JoinRequests', [
            'group' => $group,
            'requests' => $requests,
            'members' => $group->users()
                ->select('users.id', 'users.name', 'users.username', 'users.email')
                ->orderBy('users.name')
                ->get(),
        ]);
    }

    public function assignStaff(Request $request, string $uuid)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'ADMIN_ONLY_STAFF_ASSIGNMENT');

        $group = StudyGroup::query()->where('uuid', $uuid)->firstOrFail();
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_in_group' => ['required', 'in:admin,mentor,viewer'],
        ]);

        $staff = User::query()->findOrFail((int) $validated['user_id']);
        if (! $staff->isStaff()) {
            return back()->withErrors(['user_id' => 'STAFF_ROLE_REQUIRED']);
        }

        $group->staff()->syncWithoutDetaching([
            $staff->id => [
                'role_in_group' => (string) $validated['role_in_group'],
                'assigned_by' => auth()->id(),
                'updated_at' => now(),
            ],
        ]);

        CacheVersion::bump('study_groups');

        return back()->with('message', 'STUDY_GROUP_STAFF_ACCESS_GRANTED');
    }

    public function removeStaff(string $uuid, int $userId)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'ADMIN_ONLY_STAFF_ASSIGNMENT');

        $group = StudyGroup::query()->where('uuid', $uuid)->firstOrFail();
        $group->staff()->detach($userId);

        CacheVersion::bump('study_groups');

        return back()->with('message', 'STUDY_GROUP_STAFF_ACCESS_REVOKED');
    }

    public function attachRoadmap(Request $request, string $uuid)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'ADMIN_ONLY_STUDY_GROUP_MUTATION');

        $group = StudyGroup::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'roadmap_uuid' => ['required', 'uuid', 'exists:dooplab_roadmaps,uuid'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $roadmap = DoopLabRoadmap::query()
            ->where('uuid', (string) $validated['roadmap_uuid'])
            ->where('is_published', true)
            ->firstOrFail();

        $group->roadmaps()->syncWithoutDetaching([
            $roadmap->id => [
                'assigned_by_user_id' => (int) Auth::id(),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => true,
            ],
        ]);

        return back()->with('message', 'CLASS_ROADMAP_ATTACHED');
    }

    public function detachRoadmap(string $uuid, string $roadmapUuid)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'ADMIN_ONLY_STUDY_GROUP_MUTATION');

        $group = StudyGroup::query()->where('uuid', $uuid)->firstOrFail();
        $roadmap = DoopLabRoadmap::query()->where('uuid', $roadmapUuid)->firstOrFail();

        $group->roadmaps()->detach($roadmap->id);

        return back()->with('message', 'CLASS_ROADMAP_DETACHED');
    }

    public function approveRequest($uuid, $requestId)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'ADMIN_ONLY_STUDY_GROUP_MUTATION');

        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $joinRequest = StudyGroupJoinRequest::where('id', $requestId)
            ->where('study_group_id', $group->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $member = User::query()->findOrFail((int) $joinRequest->user_id);
        $isStaffMember = (bool) $member->isStaff();
        $activePlayerCount = (int) $group->users()
            ->whereNotIn('users.role', User::staffRoles())
            ->count();

        if (! $isStaffMember && $activePlayerCount >= (int) $group->max_members) {
            return back()->withErrors(['group' => 'PARTY_FULL: Kapasitas player sudah penuh.']);
        }

        $requiredLevel = (int) ($group->min_level ?? 1);
        $memberLevel = LevelingService::levelFromExp((int) ($member->exp ?? 0));

        if ($memberLevel < $requiredLevel) {
            return back()->withErrors([
                'group' => "LEVEL_TOO_LOW: User ini butuh minimal Level {$requiredLevel} untuk join group ini (level sekarang: {$memberLevel}).",
            ]);
        }

        $groupJobId = (int) ($group->job_id ?? 0);

        if ($groupJobId > 0) {
            $hasOtherJobGroups = StudyGroup::query()
                ->whereIn('id', function ($q) use ($member) {
                    $q->from('group_user')
                        ->select('study_group_id')
                        ->where('user_id', $member->id)
                        ->whereNull('deleted_at');
                })
                ->where('job_id', '!=', $groupJobId)
                ->exists();

            if ($hasOtherJobGroups) {
                return back()->withErrors(['group' => 'MEMBER_JOB_CONFLICT: User belongs to another job path group.']);
            }

            if ((int) ($member->job_id ?? 0) !== $groupJobId) {
                $member->job_id = $groupJobId;
                $member->save();
            }
        }

        $group->attachOrRestoreMember((int) $joinRequest->user_id, $member->isMentor() ? 'mentor_observer' : 'member');

        $joinRequest->update([
            'status' => 'approved',
            'processed_by' => Auth::id(),
        ]);

        CacheVersion::bump('study_groups');

        return back()->with('message', 'REQUEST_APPROVED_MEMBER_ADDED');
    }

    public function rejectRequest(Request $request, $uuid, $requestId)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'ADMIN_ONLY_STUDY_GROUP_MUTATION');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $rejectionReason = trim((string) ($validated['reason'] ?? ''));
        if ($rejectionReason === '') {
            $rejectionReason = null;
        }

        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $joinRequest = StudyGroupJoinRequest::where('id', $requestId)
            ->where('study_group_id', $group->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $joinRequest->update([
            'status' => 'rejected',
            'processed_by' => Auth::id(),
        ]);

        CacheVersion::bump('study_groups');

        $requester = $joinRequest->user()->first();
        if ($requester) {
            $requester->notify(new JoinGroupRequestRejectedNotification(
                $joinRequest->loadMissing([
                    'studyGroup:id,uuid,name',
                    'user:id,name,username',
                ]),
                $rejectionReason
            ));
        }

        return back()->with('message', 'REQUEST_REJECTED');
    }

    public function removeMember($uuid, $userId)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'ADMIN_ONLY_STUDY_GROUP_MUTATION');

        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();

        $group->softRemoveMember((int) $userId);
        StudyGroupJoinRequest::where('study_group_id', $group->id)
            ->where('user_id', (int) $userId)
            ->update([
                'status' => 'rejected',
                'processed_by' => Auth::id(),
            ]);

        CacheVersion::bump('study_groups');

        return back()->with('message', 'MEMBER_REMOVED_FROM_GROUP');
    }

    /**
     * ADMIN ACTION: Disband/Delete a study group.
     */
    public function destroy($uuid)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();

        // Pivot table otomatis terhapus jika di migration kamu pakai onDelete('cascade')
        $group->delete();

        CacheVersion::bump('study_groups');

        return back()->with('message', 'PARTY_DISBANDED');
    }

    public function restore(string $uuid)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $group = StudyGroup::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $group->restore();

        CacheVersion::bump('study_groups');

        return back()->with('message', 'PARTY_RESTORED');
    }

    public function forceDestroy(string $uuid)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $group = StudyGroup::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $group->forceDelete();

        CacheVersion::bump('study_groups');

        return back()->with('message', 'PARTY_PERMANENTLY_DELETED');
    }

    public function exportRecap($uuid, StudyGroupStaffAccessService $access)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();

        if (! $access->canAccess(auth()->user(), $group)) {
            abort(403, 'STUDY_GROUP_STAFF_ACCESS_DENIED');
        }

        $members = $group->users()
            ->whereNotIn('users.role', User::staffRoles())
            ->select('users.id', 'users.name', 'users.username', 'users.exp', 'users.level', 'users.gold')
            ->orderBy('users.name')
            ->get();

        // Quest published di group, hanya main, urut berdasarkan title
        $quests = \App\Models\Quest::query()
            ->where('study_group_id', $group->id)
            ->where('quest_type', 'main')
            ->publishedForAverage()
            ->orderBy('title')
            ->get(['id', 'title', 'quest_type']);

        $totalQuests = $quests->count();
        $questIds = $quests->pluck('id')->all();
        $userIds = $members->pluck('id')->all();

        // Latest submission per (user_id, quest_id)
        $latestSubmissions = \App\Models\Submission::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('quest_id', $questIds)
            ->joinSub(
                \App\Models\Submission::query()
                    ->whereIn('user_id', $userIds)
                    ->whereIn('quest_id', $questIds)
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('user_id', 'quest_id'),
                'latest',
                fn ($join) => $join->on('submissions.id', '=', 'latest.id')
            )
            ->get(['submissions.user_id', 'submissions.quest_id', 'submissions.grade']);

        // Index: [user_id][quest_id] => grade
        $gradeMap = [];
        foreach ($latestSubmissions as $sub) {
            $gradeMap[(int) $sub->user_id][(int) $sub->quest_id] = $sub->grade;
        }

        $filename = 'rekap-' . \Illuminate\Support\Str::slug($group->name) . '-' . now()->format('Ymd') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($members, $quests, $gradeMap, $totalQuests) {
            $out = fopen('php://output', 'w');

            // Header row: kolom tetap + satu kolom per quest
            $header = ['Nama', 'Username', 'Level', 'EXP', 'Gold'];
            foreach ($quests as $quest) {
                $header[] = $quest->title;
            }
            $header[] = 'Total Quest';
            $header[] = 'Quest Dikerjakan';
            $header[] = 'Rata-rata Grade';
            fputcsv($out, $header);

            foreach ($members as $user) {
                $userGrades = $gradeMap[$user->id] ?? [];
                $gradeSum = 0;
                $submittedCount = 0;

                $row = [$user->name, $user->username, $user->level, $user->exp, $user->gold];

                foreach ($quests as $quest) {
                    if (array_key_exists($quest->id, $userGrades)) {
                        $grade = (int) ($userGrades[$quest->id] ?? 0);
                        $row[] = $grade;
                        $gradeSum += $grade;
                        $submittedCount++;
                    } else {
                        $row[] = '';
                    }
                }

                $avg = $totalQuests > 0 ? round($gradeSum / $totalQuests, 1) : 0;
                $row[] = $totalQuests;
                $row[] = $submittedCount;
                $row[] = $avg;

                fputcsv($out, $row);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function generateUniqueInviteCode(): string
    {
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = 'GRP-' . strtoupper(Str::random(6));

            if (! StudyGroup::where('invite_code', $code)->exists()) {
                return $code;
            }
        }

        abort(500, 'FAILED_TO_GENERATE_UNIQUE_INVITE_CODE');
    }

    private function buildAttendanceDashboard(StudyGroup $group): array
    {
        $students = $group->users()
            ->whereNotIn('users.role', User::staffRoles())
            ->select('users.id', 'users.name', 'users.username', 'users.email', 'users.profile_photo')
            ->orderBy('users.name')
            ->get();

        $events = Event::query()
            ->where('study_group_id', (int) $group->id)
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('starts_at')
            ->orderBy('sequence_order')
            ->orderBy('id')
            ->get(['id', 'uuid', 'title', 'sequence_order', 'starts_at', 'ends_at']);

        $attendanceRows = EventAttendance::query()
            ->whereIn('event_id', $events->pluck('id')->all())
            ->whereIn('user_id', $students->pluck('id')->all())
            ->get(['event_id', 'user_id', 'status', 'checked_at']);

        $attendanceMap = [];
        foreach ($attendanceRows as $attendance) {
            $attendanceMap[(int) $attendance->user_id][(int) $attendance->event_id] = $attendance;
        }

        $eventPayloads = $events
            ->map(function (Event $event) use ($students, $attendanceMap) {
                $counts = [
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                    'pending' => 0,
                ];

                foreach ($students as $student) {
                    $status = (string) ($attendanceMap[(int) $student->id][(int) $event->id]?->status ?? 'pending');
                    if (! array_key_exists($status, $counts)) {
                        $status = 'pending';
                    }
                    $counts[$status]++;
                }

                $totalStudents = max(0, $students->count());
                $attendanceRate = $totalStudents > 0
                    ? round(($counts['present'] / $totalStudents) * 100)
                    : 0;

                return [
                    'id' => (int) $event->id,
                    'uuid' => (string) $event->uuid,
                    'title' => (string) ($event->title ?? ''),
                    'sequence_order' => (int) ($event->sequence_order ?? 0),
                    'starts_at' => $event->starts_at?->toISOString(),
                    'attendance_url' => route('admin.events.attendance', $event->uuid),
                    'counts' => $counts,
                    'attendance_rate' => $attendanceRate,
                ];
            })
            ->values();

        $studentPayloads = $students
            ->map(function (User $student) use ($events, $attendanceMap) {
                $counts = [
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                    'pending' => 0,
                ];

                $eventStatuses = $events->map(function (Event $event) use ($student, $attendanceMap, &$counts) {
                    $attendance = $attendanceMap[(int) $student->id][(int) $event->id] ?? null;
                    $status = (string) ($attendance?->status ?? 'pending');
                    if (! array_key_exists($status, $counts)) {
                        $status = 'pending';
                    }
                    $counts[$status]++;

                    return [
                        'event_uuid' => (string) $event->uuid,
                        'status' => $status,
                        'checked_at' => $attendance?->checked_at?->toISOString(),
                    ];
                })->values();

                $totalEvents = max(0, $events->count());
                $attendanceRate = $totalEvents > 0
                    ? round(($counts['present'] / $totalEvents) * 100)
                    : 0;

                return [
                    'id' => (int) $student->id,
                    'name' => (string) $student->name,
                    'username' => (string) ($student->username ?? ''),
                    'email' => (string) ($student->email ?? ''),
                    'profile_photo' => $student->profile_photo,
                    'attendance_rate' => $attendanceRate,
                    'counts' => $counts,
                    'events' => $eventStatuses,
                ];
            })
            ->values();

        $totalPossible = max(0, $students->count() * $events->count());
        $totalPresent = (int) $studentPayloads->sum(fn ($student) => (int) ($student['counts']['present'] ?? 0));
        $classAttendanceRate = $totalPossible > 0
            ? round(($totalPresent / $totalPossible) * 100)
            : 0;

        $lowAttendanceStudents = $events->isEmpty()
            ? 0
            : $studentPayloads->filter(fn ($student) => (int) $student['attendance_rate'] < 75)->count();

        $worstEvent = $eventPayloads
            ->sortBy('attendance_rate')
            ->first();

        return [
            'summary' => [
                'total_events' => (int) $events->count(),
                'total_students' => (int) $students->count(),
                'class_attendance_rate' => $classAttendanceRate,
                'low_attendance_students' => (int) $lowAttendanceStudents,
                'worst_event' => $worstEvent ? [
                    'title' => (string) $worstEvent['title'],
                    'attendance_rate' => (int) $worstEvent['attendance_rate'],
                ] : null,
            ],
            'events' => $eventPayloads,
            'students' => $studentPayloads,
        ];
    }

    private function buildStudentDashboard(StudyGroup $group): array
    {
        $students = $group->users()
            ->whereNotIn('users.role', User::staffRoles())
            ->orderBy('users.name')
            ->get(['users.id', 'users.username', 'users.name']);

        if ($students->isEmpty()) {
            return [];
        }

        $studentIds = $students->pluck('id')->map(fn ($id) => (int) $id)->all();
        $eventIds = Event::query()
            ->where('study_group_id', (int) $group->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $mainQuestIds = \App\Models\Quest::query()
            ->where('study_group_id', (int) $group->id)
            ->where('quest_type', 'main')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $presentCountByUser = empty($eventIds)
            ? collect()
            : EventAttendance::query()
                ->whereIn('event_id', $eventIds)
                ->whereIn('user_id', $studentIds)
                ->where('status', 'present')
                ->selectRaw('user_id, COUNT(*) as total')
                ->groupBy('user_id')
                ->pluck('total', 'user_id');

        $latestGradeSumByUser = collect();
        if (! empty($mainQuestIds)) {
            $latestGradeSumByUser = \App\Models\Submission::query()
                ->joinSub(
                    \App\Models\Submission::query()
                        ->whereIn('user_id', $studentIds)
                        ->whereIn('quest_id', $mainQuestIds)
                        ->selectRaw('MAX(id) as id')
                        ->groupBy('user_id', 'quest_id'),
                    'latest_main_submissions',
                    fn ($join) => $join->on('submissions.id', '=', 'latest_main_submissions.id')
                )
                ->selectRaw('submissions.user_id, SUM(COALESCE(submissions.grade, 0)) as grade_sum')
                ->groupBy('submissions.user_id')
                ->pluck('grade_sum', 'submissions.user_id');
        }

        $totalEvents = count($eventIds);
        $totalMainQuests = count($mainQuestIds);

        return $students
            ->map(function (User $student) use ($presentCountByUser, $latestGradeSumByUser, $totalEvents, $totalMainQuests) {
                $attendanceRate = $totalEvents > 0
                    ? round(((int) ($presentCountByUser[$student->id] ?? 0) / $totalEvents) * 100, 1)
                    : 0.0;
                $mainQuestAverage = $totalMainQuests > 0
                    ? round(((float) ($latestGradeSumByUser[$student->id] ?? 0) / $totalMainQuests), 1)
                    : 0.0;
                $performanceAverage = round(($attendanceRate + $mainQuestAverage) / 2, 1);

                return [
                    'id' => (int) $student->id,
                    'username' => (string) ($student->username ?? ''),
                    'name' => (string) $student->name,
                    'attendance_percentage' => $attendanceRate,
                    'main_quest_average' => $mainQuestAverage,
                    'performance_average' => $performanceAverage,
                    'status' => $performanceAverage < 75
                        ? 'needs_attention'
                        : 'safe',
                ];
            })
            ->values()
            ->all();
    }

   
}
