<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventImage;
use App\Models\Guide;
use App\Models\JobRole;
use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\User;
use App\Services\LmsNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class AdminEventController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum mengelola event.';

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'in:active,trash'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $view = (string) ($validated['view'] ?? 'active');

        $eventsQuery = Event::query()
            ->when($view === 'trash', fn ($query) => $query->onlyTrashed())
            ->with(['studyGroup:id,name', 'job:id,name', 'images:id,event_id,path,sort_order'])
            ->withCount(['guides', 'quests']);

        if ($this->isMentorUser()) {
            $mentorJobId = $this->requireMentorJobId();
            $eventsQuery->whereHas('studyGroup', function ($query) use ($mentorJobId) {
                $query->withTrashed();
                $query->where('job_id', $mentorJobId);
            });
        }

        $events = $eventsQuery
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('studyGroup', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('sequence_order')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $studyGroupQuery = StudyGroup::query()->orderBy('name');
        if ($this->isMentorUser()) {
            $studyGroupQuery->where('job_id', $this->requireMentorJobId());
        }

        return Inertia::render('Events/Admin/Index', [
            'events' => $events,
            'studyGroups' => $studyGroupQuery->get(['id', 'name', 'job_id']),
            'jobRoles' => JobRole::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'view' => $view,
            ],
        ]);
    }

    public function store(Request $request, LmsNotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate($this->eventRules(false));

        $this->assertMentorCanManageStudyGroupId((int) ($validated['study_group_id'] ?? 0));
        $validated = $this->normalizeEventAudiencePayload($validated);

        $payload = collect($validated)
            ->except(['images', 'remove_image_ids'])
            ->all();

        $event = Event::create($payload);
        $uploadedImages = $this->uploadedImages($request);
        $this->assertImageLimit($event, collect(), count($uploadedImages));
        $this->attachImages($event, $uploadedImages);
        $notifications->notifyEventPublished($event);

        return back()->with('message', 'EVENT_CREATED');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);

        $validated = $request->validate($this->eventRules(true));

        $this->assertMentorCanManageStudyGroupId((int) ($validated['study_group_id'] ?? 0));
        $validated = $this->normalizeEventAudiencePayload($validated);

        $payload = collect($validated)
            ->except(['images', 'remove_image_ids'])
            ->all();

        $removeImageIds = collect($validated['remove_image_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($removeImageIds->isNotEmpty()) {
            $belongsToEventCount = EventImage::query()
                ->where('event_id', (int) $event->id)
                ->whereIn('id', $removeImageIds->all())
                ->count();

            if ($belongsToEventCount !== $removeImageIds->count()) {
                throw ValidationException::withMessages([
                    'remove_image_ids' => ['One or more images are invalid for this event.'],
                ]);
            }
        }

        $uploadedImages = $this->uploadedImages($request);
        $this->assertImageLimit($event, $removeImageIds, count($uploadedImages));

        $event->update($payload);

        if ($removeImageIds->isNotEmpty()) {
            $this->removeImages($event, $removeImageIds);
        }

        $this->attachImages($event, $uploadedImages);

        return back()->with('message', 'EVENT_UPDATED');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);
        $event->delete();

        return back()->with('message', 'EVENT_DELETED');
    }

    public function restore(string $uuid): RedirectResponse
    {
        $event = Event::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->assertMentorCanAccessEvent($event);

        $event->restore();

        return back()->with('message', 'EVENT_RESTORED');
    }

    public function forceDestroy(string $uuid): RedirectResponse
    {
        $event = Event::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->assertMentorCanAccessEvent($event);

        $event->forceDelete();

        return back()->with('message', 'EVENT_PERMANENTLY_DELETED');
    }

    public function detail(Event $event): Response
    {
        $this->assertMentorCanAccessEvent($event);

        $event->load([
            'studyGroup:id,name',
            'job:id,name',
            'images:id,event_id,path,sort_order',
            'guides' => function ($q) {
                $q->select('guides.id', 'guides.uuid', 'guides.title', 'guides.study_group_id')
                    ->with('studyGroup:id,name');
            },
            'quests' => function ($q) {
                $q->select('quests.id', 'quests.uuid', 'quests.title', 'quests.status', 'quests.deadline', 'quests.study_group_id')
                    ->with('studyGroup:id,name');
            },
        ]);

        $groupId = $event->study_group_id;

        $availableGuides = Guide::query()
            ->with('studyGroup:id,name')
            ->when($groupId, function ($q) use ($groupId) {
                $q->where(function ($w) use ($groupId) {
                    $w->whereNull('study_group_id')
                        ->orWhere('study_group_id', $groupId);
                });
            })
            ->when($this->isMentorUser(), function ($q) {
                $mentorJobId = $this->requireMentorJobId();
                $q->whereHas('studyGroup', function ($sq) use ($mentorJobId) {
                    $sq->where('job_id', $mentorJobId);
                });
            })
            ->latest('id')
            ->get(['id', 'uuid', 'title', 'study_group_id']);

        $availableQuests = Quest::query()
            ->with('studyGroup:id,name')
            ->when($groupId, function ($q) use ($groupId) {
                $q->where(function ($w) use ($groupId) {
                    $w->whereNull('study_group_id')
                        ->orWhere('study_group_id', $groupId);
                });
            })
            ->when($this->isMentorUser(), function ($q) {
                $mentorJobId = $this->requireMentorJobId();
                $q->where(function ($w) use ($mentorJobId) {
                    $w->whereHas('studyGroup', function ($sg) use ($mentorJobId) {
                        $sg->where('job_id', $mentorJobId);
                    })->orWhereHas('taskBank', function ($tb) use ($mentorJobId) {
                        $tb->where('job_role_id', $mentorJobId);
                    });
                });
            })
            ->latest('id')
            ->get(['id', 'uuid', 'title', 'status', 'deadline', 'study_group_id']);

        return Inertia::render('Events/Admin/Detail', [
            'event' => $event,
            'availableGuides' => $availableGuides,
            'availableQuests' => $availableQuests,
        ]);
    }

    public function attendance(Event $event): Response
    {
        $this->assertMentorCanAccessEvent($event);

        $event->load(['studyGroup:id,name', 'attendances']);

        $attendanceUsers = collect();
        if ($event->study_group_id) {
            $attendanceUsers = User::query()
                ->select('users.id', 'users.name', 'users.profile_photo')
                ->whereIn('users.id', function ($q) use ($event) {
                    $q->from('group_user')
                        ->select('user_id')
                        ->where('study_group_id', $event->study_group_id)
                        ->whereNull('deleted_at');
                })
                ->orderBy('users.name')
                ->get()
                ->map(function ($user) use ($event) {
                    $status = $event->attendances->firstWhere('user_id', $user->id)?->status ?? 'pending';
                    $user->attendance_status = $status;
                    return $user;
                });
        }

        return Inertia::render('Events/Admin/Attendance', [
            'event' => $event,
            'attendanceUsers' => $attendanceUsers,
        ]);
    }

    public function attachGuides(Request $request, Event $event): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);

        $validated = $request->validate([
            'guide_ids' => ['required', 'array', 'min:1'],
            'guide_ids.*' => ['integer', 'exists:guides,id'],
        ]);

        $allowedIds = Guide::query()
            ->whereIn('id', $validated['guide_ids'])
            ->when($event->study_group_id, function ($q) use ($event) {
                $q->where(function ($w) use ($event) {
                    $w->whereNull('study_group_id')
                        ->orWhere('study_group_id', $event->study_group_id);
                });
            })
            ->pluck('id')
            ->all();

        $maxOrder = (int) $event->guides()->max('event_guide.sort_order');
        $syncData = [];
        foreach ($allowedIds as $guideId) {
            $maxOrder++;
            $syncData[$guideId] = ['sort_order' => $maxOrder];
        }

        if (!empty($syncData)) {
            $event->guides()->syncWithoutDetaching($syncData);
        }

        return back()->with('message', 'EVENT_GUIDES_ATTACHED');
    }

    public function attachQuests(Request $request, Event $event): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);

        $validated = $request->validate([
            'quest_ids' => ['required', 'array', 'min:1'],
            'quest_ids.*' => ['integer', 'exists:quests,id'],
        ]);

        $allowedIds = Quest::query()
            ->whereIn('id', $validated['quest_ids'])
            ->when($event->study_group_id, function ($q) use ($event) {
                $q->where(function ($w) use ($event) {
                    $w->whereNull('study_group_id')
                        ->orWhere('study_group_id', $event->study_group_id);
                });
            })
            ->pluck('id')
            ->all();

        $maxOrder = (int) $event->quests()->max('event_quest.sort_order');
        $syncData = [];
        foreach ($allowedIds as $questId) {
            $maxOrder++;
            $syncData[$questId] = ['sort_order' => $maxOrder];
        }

        if (!empty($syncData)) {
            $event->quests()->syncWithoutDetaching($syncData);
        }

        return back()->with('message', 'EVENT_QUESTS_ATTACHED');
    }

    public function detachGuide(Event $event, Guide $guide): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);

        $event->guides()->detach($guide->id);

        return back()->with('message', 'EVENT_GUIDE_DETACHED');
    }

    public function detachQuest(Event $event, Quest $quest): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);

        $event->quests()->detach($quest->id);

        return back()->with('message', 'EVENT_QUEST_DETACHED');
    }

    public function reorderGuides(Request $request, Event $event): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);

        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'exists:guides,id'],
            'orders.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($event, $validated) {
            foreach ($validated['orders'] as $item) {
                $event->guides()->updateExistingPivot((int) $item['id'], [
                    'sort_order' => (int) $item['sort_order'],
                ]);
            }
        });

        return back()->with('message', 'EVENT_GUIDES_REORDERED');
    }

    public function reorderQuests(Request $request, Event $event): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);

        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'exists:quests,id'],
            'orders.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($event, $validated) {
            foreach ($validated['orders'] as $item) {
                $event->quests()->updateExistingPivot((int) $item['id'], [
                    'sort_order' => (int) $item['sort_order'],
                ]);
            }
        });

        return back()->with('message', 'EVENT_QUESTS_REORDERED');
    }

    public function updateAttendance(Request $request, Event $event): RedirectResponse
    {
        $this->assertMentorCanAccessEvent($event);

        $validated = $request->validate([
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'attendance.*.status' => ['required', 'in:pending,present,absent,excused'],
        ]);

        $allowedUserIds = collect();
        if ($event->study_group_id) {
            $allowedUserIds = User::query()
                ->whereIn('id', function ($q) use ($event) {
                    $q->from('group_user')
                        ->select('user_id')
                        ->where('study_group_id', $event->study_group_id)
                        ->whereNull('deleted_at');
                })
                ->pluck('id');
        }

        $rows = collect($validated['attendance'])
            ->filter(function ($row) use ($event, $allowedUserIds) {
                if (! $event->study_group_id) {
                    return false;
                }
                return $allowedUserIds->contains((int) $row['user_id']);
            })
            ->map(function ($row) use ($event) {
                return [
                    'event_id' => $event->id,
                    'user_id' => (int) $row['user_id'],
                    'status' => $row['status'],
                    'checked_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            })
            ->values()
            ->all();

        if (!empty($rows)) {
            EventAttendance::query()->upsert(
                $rows,
                ['event_id', 'user_id'],
                ['status', 'checked_at', 'updated_at']
            );
        }

        return back()->with('message', 'EVENT_ATTENDANCE_UPDATED');
    }

    private function isMentorUser(): bool
    {
        return (bool) auth()->user()?->isMentor();
    }

    private function requireMentorJobId(): int
    {
        $jobId = (int) (auth()->user()?->job_id ?? 0);
        abort_if($jobId <= 0, 403, self::MENTOR_JOB_REQUIRED_MESSAGE);
        return $jobId;
    }

    private function assertMentorCanAccessEvent(Event $event): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();
        $groupJobId = (int) StudyGroup::withTrashed()
            ->whereKey((int) ($event->study_group_id ?? 0))
            ->value('job_id');

        abort_unless($groupJobId === $mentorJobId, 403, 'MENTOR_CANNOT_ACCESS_EVENT_OUTSIDE_JOB');
    }

    private function assertMentorCanManageStudyGroupId(int $studyGroupId): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();
        if ($studyGroupId <= 0) {
            abort(403, 'MENTOR_EVENT_MUST_HAVE_STUDY_GROUP');
        }

        $isAllowed = StudyGroup::query()
            ->whereKey($studyGroupId)
            ->where('job_id', $mentorJobId)
            ->exists();

        abort_unless($isAllowed, 403, 'MENTOR_CANNOT_MANAGE_EVENT_OUTSIDE_JOB');
    }

    private function normalizeEventAudiencePayload(array $validated): array
    {
        $studyGroupId = (int) ($validated['study_group_id'] ?? 0);
        $jobId = (int) ($validated['job_id'] ?? 0);
        $validated['self_attendance_enabled'] = (bool) ($validated['self_attendance_enabled'] ?? false);

        if ($studyGroupId > 0) {
            $studyGroup = StudyGroup::query()->select('id', 'job_id')->findOrFail($studyGroupId);
            $validated['job_id'] = (int) ($studyGroup->job_id ?? 0) ?: null;

            return $validated;
        }

        if ($jobId <= 0) {
            throw ValidationException::withMessages([
                'job_id' => 'Pilih target job untuk event publik.',
            ]);
        }

        return $validated;
    }

    private function eventRules(bool $isUpdate): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sequence_order' => ['required', 'integer', 'min:1'],
            'study_group_id' => ['nullable', 'exists:study_groups,id'],
            'job_id' => ['nullable', 'exists:job_roles,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'self_attendance_enabled' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => [
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(4096),
            ],
        ];

        if ($isUpdate) {
            $rules['remove_image_ids'] = ['nullable', 'array'];
            $rules['remove_image_ids.*'] = ['integer', 'exists:event_images,id'];
        }

        return $rules;
    }

    /**
     * @return UploadedFile[]
     */
    private function uploadedImages(Request $request): array
    {
        $images = $request->file('images', []);
        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter(
            $images,
            fn ($image) => $image instanceof UploadedFile
        ));
    }

    private function assertImageLimit(Event $event, Collection $removeImageIds, int $newImageCount): void
    {
        $currentCount = (int) $event->images()->count();
        $removedCount = $removeImageIds->isEmpty()
            ? 0
            : (int) $event->images()->whereIn('id', $removeImageIds->all())->count();

        $finalCount = $currentCount - $removedCount + $newImageCount;

        if ($finalCount > 8) {
            throw ValidationException::withMessages([
                'images' => ['Maximum 8 images are allowed for one event.'],
            ]);
        }
    }

    private function removeImages(Event $event, Collection $removeImageIds): void
    {
        $images = EventImage::query()
            ->where('event_id', (int) $event->id)
            ->whereIn('id', $removeImageIds->all())
            ->get(['id', 'path']);

        $paths = $images->pluck('path')
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->values()
            ->all();

        if (! empty($paths)) {
            Storage::disk('public')->delete($paths);
        }

        if ($images->isNotEmpty()) {
            EventImage::query()
                ->whereIn('id', $images->pluck('id')->all())
                ->delete();
        }
    }

    /**
     * @param  UploadedFile[]  $images
     */
    private function attachImages(Event $event, array $images): void
    {
        if (empty($images)) {
            return;
        }

        $nextSortOrder = (int) $event->images()->max('sort_order');

        foreach ($images as $image) {
            $nextSortOrder++;
            $path = $image->store('events', 'public');

            EventImage::query()->create([
                'event_id' => (int) $event->id,
                'path' => $path,
                'sort_order' => $nextSortOrder,
            ]);
        }
    }
}
