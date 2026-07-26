<?php

namespace App\Http\Controllers;

use App\Models\JobRole;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminJobRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->abortNonSuperAdmin($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $jobs = JobRole::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->withCount(['users', 'studyGroups'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Jobs/Admin/Index', [
            'jobs' => $jobs,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(JobRole $jobRole): Response
    {
        $this->abortNonSuperAdmin(request());

        $jobRole->loadCount([
            'users',
            'studyGroups',
            'taskBanks',
        ]);

        $groups = StudyGroup::query()
            ->where('job_id', (int) $jobRole->id)
            ->withCount([
                'staff as staff_count',
                'users as students_count' => fn ($query) => $query->whereNotIn('users.role', User::staffRoles()),
                'quests as quests_count',
                'events as events_count',
            ])
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'description', 'invite_code', 'max_members', 'min_level', 'job_id'])
            ->map(fn (StudyGroup $group) => [
                'id' => (int) $group->id,
                'uuid' => (string) $group->uuid,
                'name' => (string) $group->name,
                'description' => (string) ($group->description ?? ''),
                'invite_code' => (string) ($group->invite_code ?? ''),
                'max_members' => (int) ($group->max_members ?? 0),
                'min_level' => (int) ($group->min_level ?? 1),
                'students_count' => (int) ($group->students_count ?? 0),
                'staff_count' => (int) ($group->staff_count ?? 0),
                'quests_count' => (int) ($group->quests_count ?? 0),
                'events_count' => (int) ($group->events_count ?? 0),
                'detail_url' => route('groups.detail', $group->uuid),
                'user_preview_url' => route('groups.user-preview', $group->uuid),
                'quests_url' => route('groups.quests.index', $group->uuid),
                'guides_url' => route('groups.guides.index', $group->uuid),
                'events_url' => route('groups.events.index', $group->uuid),
            ])
            ->values();

        return Inertia::render('Jobs/Admin/Show', [
            'job' => [
                'id' => (int) $jobRole->id,
                'name' => (string) $jobRole->name,
                'slug' => (string) $jobRole->slug,
                'status' => (string) ($jobRole->status ?? JobRole::STATUS_ACTIVE),
                'description' => (string) ($jobRole->description ?? ''),
                'emblem_path' => $jobRole->emblem_path,
                'users_count' => (int) ($jobRole->users_count ?? 0),
                'study_groups_count' => (int) ($jobRole->study_groups_count ?? 0),
                'task_banks_count' => (int) ($jobRole->task_banks_count ?? 0),
            ],
            'groups' => $groups,
            'studyGroupsUrl' => route('groups.manage', ['search' => $jobRole->name]),
            'jobsUrl' => route('admin.jobs.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->abortNonSuperAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:job_roles,name'],
            'status' => ['required', Rule::in(JobRole::statuses())],
            'description' => ['nullable', 'string'],
            'emblem' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $emblemPath = null;
        if ($request->hasFile('emblem')) {
            $emblemPath = $request->file('emblem')->store('jobs', 'public');
        }

        JobRole::create([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name']),
            'status' => $validated['status'],
            'description' => $validated['description'] ?? null,
            'emblem_path' => $emblemPath,
        ]);

        CacheVersion::bump('landing');

        return back()->with('message', 'JOB_CREATED');
    }

    public function update(Request $request, JobRole $jobRole): RedirectResponse
    {
        $this->abortNonSuperAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:job_roles,name,' . $jobRole->id],
            'status' => ['required', Rule::in(JobRole::statuses())],
            'description' => ['nullable', 'string'],
            'emblem' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $newSlug = Str::slug($validated['name']);
        if ($newSlug !== $jobRole->slug) {
            $newSlug = $this->generateUniqueSlug($validated['name'], $jobRole->id);
        }

        $emblemPath = $jobRole->emblem_path;
        if ($request->hasFile('emblem')) {
            if ($emblemPath && Storage::disk('public')->exists($emblemPath)) {
                Storage::disk('public')->delete($emblemPath);
            }
            $emblemPath = $request->file('emblem')->store('jobs', 'public');
        }

        $jobRole->update([
            'name' => $validated['name'],
            'slug' => $newSlug,
            'status' => $validated['status'],
            'description' => $validated['description'] ?? null,
            'emblem_path' => $emblemPath,
        ]);

        CacheVersion::bump('landing');

        return back()->with('message', 'JOB_UPDATED');
    }

    public function destroy(JobRole $jobRole): RedirectResponse
    {
        $this->abortNonSuperAdmin(request());

        $jobRole->delete();

        CacheVersion::bump('landing');

        return back()->with('message', 'JOB_DELETED');
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (
            JobRole::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function abortNonSuperAdmin(Request $request): void
    {
        abort_unless(
            (string) ($request->user()?->role ?? '') === \App\Models\User::ROLE_SUPER_ADMIN,
            403,
            'SUPER_ADMIN_ONLY_JOB_COMMAND'
        );
    }
}
