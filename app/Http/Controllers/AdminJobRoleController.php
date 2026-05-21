<?php

namespace App\Http\Controllers;

use App\Models\JobRole;
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

    public function store(Request $request): RedirectResponse
    {
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
}
