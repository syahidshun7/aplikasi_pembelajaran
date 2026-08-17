<?php

namespace App\Http\Controllers;

use App\Models\DoopNewsPost;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminDoopNewsController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:all,'.implode(',', DoopNewsPost::statuses())],
            'category' => ['nullable', 'string', 'in:all,'.implode(',', DoopNewsPost::categories())],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $status = (string) ($validated['status'] ?? 'pending');
        $category = (string) ($validated['category'] ?? 'all');
        $search = trim((string) ($validated['search'] ?? ''));

        $posts = DoopNewsPost::query()
            ->with(['author:id,name,username,role', 'reviewer:id,name,username'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($category !== 'all', fn ($query) => $query->where('category', $category))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('DoopNews/Admin/Index', [
            'posts' => $posts,
            'filters' => [
                'status' => $status,
                'category' => $category,
                'search' => $search,
            ],
            'categories' => DoopNewsPost::categories(),
            'statuses' => DoopNewsPost::statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPost($request);
        $coverImagePath = $this->storeCoverImage($request);

        DoopNewsPost::create([
            ...$validated,
            'cover_image_path' => $coverImagePath,
            'author_id' => (int) $request->user()->id,
            'reviewer_id' => (int) $request->user()->id,
            'status' => DoopNewsPost::STATUS_PUBLISHED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'published_at' => now(),
        ]);
        CacheVersion::bump('home');

        return back()->with('message', 'DOOPNEWS_PUBLISHED');
    }

    public function update(Request $request, DoopNewsPost $post): RedirectResponse
    {
        $validated = $this->validatedPost($request);
        $coverImagePath = $this->storeCoverImage($request, $post->cover_image_path);

        $post->fill($validated);
        if ($coverImagePath !== null) {
            $post->cover_image_path = $coverImagePath;
        }
        if ($post->status === DoopNewsPost::STATUS_PUBLISHED && ! $post->published_at) {
            $post->published_at = now();
        }
        $post->save();
        CacheVersion::bump('home');

        return back()->with('message', 'DOOPNEWS_UPDATED');
    }

    public function publish(Request $request, DoopNewsPost $post): RedirectResponse
    {
        $post->forceFill([
            'status' => DoopNewsPost::STATUS_PUBLISHED,
            'reviewer_id' => (int) $request->user()->id,
            'reviewed_at' => now(),
            'published_at' => now(),
            'rejection_reason' => null,
        ])->save();
        CacheVersion::bump('home');

        return back()->with('message', 'DOOPNEWS_PUBLISHED');
    }

    public function reject(Request $request, DoopNewsPost $post): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $post->forceFill([
            'status' => DoopNewsPost::STATUS_REJECTED,
            'reviewer_id' => (int) $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => (string) ($validated['rejection_reason'] ?? ''),
        ])->save();
        CacheVersion::bump('home');

        return back()->with('message', 'DOOPNEWS_REJECTED');
    }

    public function destroy(DoopNewsPost $post): RedirectResponse
    {
        $post->delete();
        CacheVersion::bump('home');

        return back()->with('message', 'DOOPNEWS_DELETED');
    }

    private function validatedPost(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'in:'.implode(',', DoopNewsPost::categories())],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:30000'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'version_label' => ['nullable', 'string', 'max:80'],
            'action_label' => ['nullable', 'string', 'max:80'],
            'action_url' => ['nullable', 'string', 'max:1000'],
        ]);

        unset($validated['cover_image']);

        return $validated;
    }

    private function storeCoverImage(Request $request, ?string $currentPath = null): ?string
    {
        if (! $request->hasFile('cover_image')) {
            return null;
        }

        if ($currentPath && Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        return $request->file('cover_image')->store('doopnews', 'public');
    }
}
