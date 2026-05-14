<?php
namespace App\Http\Controllers;

use App\Events\JoinGroupRequested;
use App\Models\StudyGroup;
use App\Models\StudyGroupJoinRequest;
use App\Models\User;
use App\Services\LevelingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class StudyGroupController extends Controller
{
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

        $groups->getCollection()->transform(function (StudyGroup $group) use ($userGroupIds, $groupRequestStatuses) {
            $payload = $group->toArray();
            $groupId = (int) $group->id;
            $payload['is_member'] = in_array($groupId, $userGroupIds, true);
            $payload['join_request_status'] = $groupRequestStatuses[$groupId] ?? null;
            $payload['min_level'] = (int) ($group->min_level ?? 1);
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

        if ($userLevel < $minLevel) {
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
}
