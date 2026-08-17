<?php

namespace App\Http\Controllers;

use App\Events\DailyQuestActivityTriggered;
use App\Models\DailyQuestDefinition;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventCheckInCode;
use App\Models\StudyGroup;
use App\Models\UserContentRead;
use App\Services\StudyGroupStaffAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserEventController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $canManageMembership = $user && ! $user->isStaff();
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
            ->orderByDesc('created_at')
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('starts_at')
            ->orderByDesc('sequence_order')
            ->paginate(12)
            ->withQueryString();

        $eventIds = collect($events->items())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $seenEventIdSet = UserContentRead::seenContentIds((int) ($user?->id ?? 0), UserContentRead::TYPE_EVENT, $eventIds)
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        $events->getCollection()->transform(function (Event $event) use ($seenEventIdSet) {
            $event->is_new_for_user = $this->isEventNewForUser($event, $seenEventIdSet);
            return $event;
        });

        return Inertia::render('Events/UserIndex', [
            'events' => $events,
            'filters' => [
                'search' => $search,
                'class_group_id' => $classGroupId > 0 ? $classGroupId : null,
            ],
            'classGroups' => $availableClassGroups,
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        $user = Auth::user();
        $this->ensureUserCanAccessEvent($event, $user);
        if (! $user?->isStaff()) {
            UserContentRead::markSeen((int) ($user?->id ?? 0), UserContentRead::TYPE_EVENT, (int) $event->id);
        }

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
        $canCodeAttend = ! $canSelfAttend
            && ! in_array($attendanceStatus, ['present', 'absent', 'excused'], true);
        $activeCheckInCode = EventCheckInCode::query()
            ->where('event_id', (int) $event->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return Inertia::render('Events/UserShow', [
            'event' => $event,
            'userAttendance' => [
                'status' => $attendanceStatus,
                'checked_at' => $attendance?->checked_at?->toISOString(),
                'can_self_attend' => $canSelfAttend,
                'can_code_attend' => $canCodeAttend,
                'check_in_code_available' => (bool) $activeCheckInCode,
                'check_in_code_expires_at' => $activeCheckInCode?->expires_at?->toISOString(),
                'qr_check_in_token' => trim((string) $request->query('check_in_token', '')),
            ],
        ]);
    }

    public function userPreview(Request $request, Event $event): Response
    {
        $this->authorizeStaffPreviewAccess($request, $event);

        $event->load([
            'studyGroup:id,uuid,name',
            'job:id,name',
            'images:id,event_id,path,sort_order',
            'guides' => function ($q) {
                $q->select('guides.id', 'guides.uuid', 'guides.title', 'guides.description', 'guides.file_path', 'guides.google_docs_embed_url', 'guides.study_group_id')
                    ->with('studyGroup:id,name');
            },
            'quests' => function ($q) {
                $q->select('quests.id', 'quests.uuid', 'quests.title', 'quests.description', 'quests.difficulty', 'quests.status', 'quests.deadline', 'quests.reward_gold', 'quests.reward_exp', 'quests.study_group_id')
                    ->with('studyGroup:id,name');
            },
        ]);

        return Inertia::render('Events/UserShow', [
            'event' => $event,
            'previewMode' => true,
            'backUrl' => $event->studyGroup
                ? route('groups.events.index', $event->studyGroup->uuid)
                : route('admin.events.index'),
            'userAttendance' => [
                'status' => 'preview',
                'checked_at' => null,
                'can_self_attend' => false,
                'can_code_attend' => false,
                'check_in_code_available' => false,
                'check_in_code_expires_at' => null,
                'qr_check_in_token' => '',
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

        $this->recordPresentAttendance($event, $attendance, (int) $user->id);

        return back()->with('message', 'EVENT_SELF_ATTENDANCE_RECORDED');
    }

    public function codeAttend(Request $request, Event $event): RedirectResponse
    {
        $user = Auth::user();
        if ($response = $this->denyStaffPlayModeAttendance($user)) {
            return $response;
        }
        $this->ensureUserCanAccessEvent($event, $user);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'token' => ['nullable', 'string', 'max:120'],
        ]);

        $code = trim((string) ($validated['code'] ?? ''));
        $token = trim((string) ($validated['token'] ?? ''));

        if ($code === '' && $token === '') {
            return back()->withErrors(['code' => 'Masukkan kode check-in atau scan QR terlebih dahulu.']);
        }

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

        $matchedCode = $this->resolveValidCheckInCode($event, $code, $token);

        if (! $matchedCode) {
            return back()->withErrors(['code' => 'Kode check-in tidak valid atau sudah expired.']);
        }

        $this->recordPresentAttendance($event, $attendance, (int) $user->id);

        return back()->with('message', 'EVENT_CHECK_IN_ATTENDANCE_RECORDED');
    }

    public function qrAttend(Request $request, Event $event, string $token): RedirectResponse
    {
        $user = Auth::user();
        if ($response = $this->denyStaffPlayModeAttendance($user)) {
            return $response;
        }
        $this->ensureUserCanAccessEvent($event, $user);

        $attendance = EventAttendance::query()->firstOrNew([
            'event_id' => (int) $event->id,
            'user_id' => (int) $user->id,
        ]);

        $currentStatus = (string) ($attendance->status ?? 'pending');

        if (in_array($currentStatus, ['absent', 'excused'], true)) {
            return redirect()
                ->route('events.show', $event->uuid)
                ->with('message', 'EVENT_ATTENDANCE_ALREADY_FINALIZED');
        }

        if ($currentStatus === 'present') {
            return redirect()
                ->route('events.show', $event->uuid)
                ->with('message', 'EVENT_SELF_ATTENDANCE_RECORDED');
        }

        $matchedCode = $this->resolveValidCheckInCode($event, '', trim($token));

        if (! $matchedCode) {
            return redirect()
                ->route('events.show', $event->uuid)
                ->withErrors(['code' => 'QR check-in tidak valid atau sudah expired.']);
        }

        $this->recordPresentAttendance($event, $attendance, (int) $user->id);

        return redirect()
            ->route('events.show', $event->uuid)
            ->with('message', 'EVENT_QR_ATTENDANCE_RECORDED');
    }

    private function ensureUserCanAccessEvent(Event $event, $user): void
    {
        $canManageMembership = $user && ! $user->isStaff();
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

    private function authorizeStaffPreviewAccess(Request $request, Event $event): void
    {
        $user = $request->user();

        abort_unless($user?->isStaff(), 403, 'EVENT_PREVIEW_STAFF_ONLY');

        if (! $event->study_group_id) {
            abort_unless($user->isAdmin(), 403, 'GLOBAL_EVENT_PREVIEW_ADMIN_ONLY');
            return;
        }

        $event->loadMissing('studyGroup');

        abort_unless(
            app(StudyGroupStaffAccessService::class)->canAccess($user, $event->studyGroup),
            403,
            'EVENT_PREVIEW_ACCESS_DENIED'
        );
    }

    private function recordPresentAttendance(Event $event, EventAttendance $attendance, int $userId): void
    {
        $attendance->status = 'present';
        $attendance->checked_at = now();
        $attendance->save();

        event(new DailyQuestActivityTriggered(
            $userId,
            DailyQuestDefinition::ACTIVITY_EVENT_ATTENDANCE,
            1,
            [
                'event_uuid' => (string) $event->uuid,
                'attendance_status' => 'present',
            ],
        ));
    }

    private function resolveValidCheckInCode(Event $event, string $code = '', string $token = ''): ?EventCheckInCode
    {
        $code = trim($code);
        $token = trim($token);

        return EventCheckInCode::query()
            ->where('event_id', (int) $event->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->get()
            ->first(function (EventCheckInCode $checkInCode) use ($code, $token) {
                if ($token !== '' && hash_equals((string) $checkInCode->qr_token, $token)) {
                    return true;
                }

                return $code !== '' && Hash::check($code, (string) $checkInCode->code_hash);
            });
    }

    private function denyStaffPlayModeAttendance($user): ?RedirectResponse
    {
        if (! (bool) $user?->isStaffPlayMode()) {
            return null;
        }

        return back()->withErrors([
            'event' => 'Staff play mode tidak bisa mencatat attendance pemain.',
        ]);
    }

    private function isEventNewForUser(Event $event, array $seenEventIdSet): bool
    {
        $eventId = (int) $event->id;
        if ($eventId <= 0 || isset($seenEventIdSet[$eventId])) {
            return false;
        }

        return $event->created_at !== null && $event->created_at->gte(now()->subDays(30));
    }
}
