<?php

namespace App\Http\Controllers;

use App\Events\DailyQuestActivityTriggered;
use App\Models\DailyQuestDefinition;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserEventController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $isStaffPlayMode = (bool) $user?->isStaffPlayMode();
        $canManageMembership = $user && (! $isStaffPlayMode || (bool) $user->isMentor());
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'class_group_id' => ['nullable', 'integer'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $classGroupId = (int) ($validated['class_group_id'] ?? 0);
        $userJobId = $user?->job_id;
        $userGroupIds = $canManageMembership
            ? $user->studyGroups()
                ->where('study_groups.job_id', $userJobId)
                ->pluck('study_groups.id')
                ->toArray()
            : [];
        $availableClassGroups = StudyGroup::query()
            ->whereIn('id', $userGroupIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($classGroupId > 0 && !in_array($classGroupId, $userGroupIds, true)) {
            $classGroupId = 0;
        }

        $events = Event::query()
            ->where(function ($query) use ($userGroupIds, $userJobId) {
                $query->where(function ($publicQuery) use ($userJobId) {
                    $publicQuery->whereNull('study_group_id')
                        ->where(function ($audienceQuery) use ($userJobId) {
                            $audienceQuery->whereNull('job_id');

                            if (! is_null($userJobId)) {
                                $audienceQuery->orWhere('job_id', $userJobId);
                            }
                        });
                })
                    ->orWhereIn('study_group_id', $userGroupIds);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('studyGroup', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($classGroupId > 0, function ($query) use ($classGroupId) {
                $query->where('study_group_id', $classGroupId);
            })
            ->with(['studyGroup:id,name', 'job:id,name'])
            ->withCount(['guides', 'quests'])
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('starts_at')
            ->orderBy('sequence_order')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Events/UserIndex', [
            'events' => $events,
            'filters' => [
                'search' => $search,
                'class_group_id' => $classGroupId > 0 ? $classGroupId : null,
            ],
            'classGroups' => $availableClassGroups,
        ]);
    }

    public function show(Event $event): Response
    {
        $user = Auth::user();
        $this->ensureUserCanAccessEvent($event, $user);

        $event->load([
            'studyGroup:id,name',
            'job:id,name',
            'images:id,event_id,path,sort_order',
            'guides' => function ($q) use ($event) {
                $q->select('guides.id', 'guides.uuid', 'guides.title', 'guides.description', 'guides.file_path', 'guides.google_docs_embed_url', 'guides.study_group_id')
                    ->with('studyGroup:id,name');
            },
            'quests' => function ($q) use ($event) {
                $q->select('quests.id', 'quests.uuid', 'quests.title', 'quests.description', 'quests.difficulty', 'quests.status', 'quests.deadline', 'quests.reward_gold', 'quests.reward_exp', 'quests.study_group_id')
                    ->with('studyGroup:id,name');
            },
        ]);

        $attendance = EventAttendance::query()
            ->where('event_id', (int) $event->id)
            ->where('user_id', (int) $user->id)
            ->first();

        $attendanceStatus = (string) ($attendance?->status ?? 'pending');
        $canSelfAttend = (bool) $event->self_attendance_enabled
            && ! in_array($attendanceStatus, ['present', 'absent', 'excused'], true);

        return Inertia::render('Events/UserShow', [
            'event' => $event,
            'userAttendance' => [
                'status' => $attendanceStatus,
                'checked_at' => $attendance?->checked_at?->toISOString(),
                'can_self_attend' => $canSelfAttend,
            ],
        ]);
    }

    public function selfAttend(Request $request, Event $event): RedirectResponse
    {
        $user = Auth::user();
        if ((bool) $user?->isStaffPlayMode()) {
            return back()->withErrors([
                'event' => 'Staff play mode tidak bisa mencatat attendance pemain.',
            ]);
        }

        $this->ensureUserCanAccessEvent($event, $user);

        abort_unless((bool) $event->self_attendance_enabled, 403, 'EVENT_SELF_ATTENDANCE_DISABLED');

        $attendance = EventAttendance::query()->firstOrNew([
            'event_id' => (int) $event->id,
            'user_id' => (int) $user->id,
        ]);

        $currentStatus = (string) ($attendance->status ?? 'pending');

        if (in_array($currentStatus, ['absent', 'excused'], true)) {
            return back()->with('message', 'EVENT_ATTENDANCE_ALREADY_FINALIZED');
        }

        if ($currentStatus === 'present') {
            return back()->with('message', 'EVENT_SELF_ATTENDANCE_RECORDED');
        }

        $attendance->status = 'present';
        $attendance->checked_at = now();
        $attendance->save();

        event(new DailyQuestActivityTriggered(
            (int) $user->id,
            DailyQuestDefinition::ACTIVITY_EVENT_ATTENDANCE,
            1,
            [
                'event_uuid' => (string) $event->uuid,
                'attendance_status' => 'present',
            ],
        ));

        return back()->with('message', 'EVENT_SELF_ATTENDANCE_RECORDED');
    }

    private function ensureUserCanAccessEvent(Event $event, $user): void
    {
        $isStaffPlayMode = (bool) $user?->isStaffPlayMode();
        $canManageMembership = $user && (! $isStaffPlayMode || (bool) $user->isMentor());
        $userJobId = $user?->job_id;
        $userGroupIds = $canManageMembership
            ? $user->studyGroups()
                ->where('study_groups.job_id', $userJobId)
                ->pluck('study_groups.id')
                ->toArray()
            : [];

        $isAccessible = (
            is_null($event->study_group_id)
            && (
                is_null($event->job_id)
                || (! is_null($userJobId) && (int) $event->job_id === (int) $userJobId)
            )
        ) || in_array((int) $event->study_group_id, $userGroupIds, true);

        abort_unless($isAccessible, 403, 'EVENT_ACCESS_DENIED');
    }
}
