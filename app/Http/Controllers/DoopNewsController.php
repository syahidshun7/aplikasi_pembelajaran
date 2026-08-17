<?php

namespace App\Http\Controllers;

use App\Models\DoopNewsPost;
use App\Models\UserContentRead;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DoopNewsController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'in:all,'.implode(',', DoopNewsPost::categories())],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $category = (string) ($validated['category'] ?? 'all');
        $search = trim((string) ($validated['search'] ?? ''));

        $posts = DoopNewsPost::query()
            ->published()
            ->with('author:id,name,username,role,profile_photo')
            ->when($category !== 'all', fn ($query) => $query->where('category', $category))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $postIds = collect($posts->items())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $seenPostIdSet = UserContentRead::seenContentIds((int) $request->user()->id, UserContentRead::TYPE_DOOP_NEWS, $postIds)
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        $posts->getCollection()->transform(function (DoopNewsPost $post) use ($seenPostIdSet) {
            $post->is_new_for_user = $this->isDoopNewsNewForUser($post, $seenPostIdSet);
            return $post;
        });

        foreach ($postIds as $postId) {
            UserContentRead::markSeen((int) $request->user()->id, UserContentRead::TYPE_DOOP_NEWS, $postId);
        }

        return Inertia::render('DoopNews/Index', [
            'posts' => $posts,
            'filters' => [
                'category' => $category,
                'search' => $search,
            ],
            'categories' => DoopNewsPost::categories(),
        ]);
    }

    public function show(DoopNewsPost $post): Response
    {
        abort_unless($post->status === DoopNewsPost::STATUS_PUBLISHED && $post->published_at?->lte(now()), 404);

        $post->load('author:id,name,username,role,profile_photo');
        UserContentRead::markSeen((int) request()->user()->id, UserContentRead::TYPE_DOOP_NEWS, (int) $post->id);

        return Inertia::render('DoopNews/Show', [
            'post' => $post,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('DoopNews/Submit', [
            'categories' => [
                DoopNewsPost::CATEGORY_ANNOUNCEMENT,
                DoopNewsPost::CATEGORY_EVENT,
                DoopNewsPost::CATEGORY_CLASS,
                DoopNewsPost::CATEGORY_QUEST,
                DoopNewsPost::CATEGORY_COMMUNITY,
            ],
        ]);
    }

    public function mine(Request $request): Response
    {
        $posts = DoopNewsPost::query()
            ->where('author_id', (int) $request->user()->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('DoopNews/MyPosts', [
            'posts' => $posts,
            'categories' => [
                DoopNewsPost::CATEGORY_ANNOUNCEMENT,
                DoopNewsPost::CATEGORY_EVENT,
                DoopNewsPost::CATEGORY_CLASS,
                DoopNewsPost::CATEGORY_QUEST,
                DoopNewsPost::CATEGORY_COMMUNITY,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'in:'.implode(',', [
                DoopNewsPost::CATEGORY_ANNOUNCEMENT,
                DoopNewsPost::CATEGORY_EVENT,
                DoopNewsPost::CATEGORY_CLASS,
                DoopNewsPost::CATEGORY_QUEST,
                DoopNewsPost::CATEGORY_COMMUNITY,
            ])],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'action_label' => ['nullable', 'string', 'max:80'],
            'action_url' => ['nullable', 'string', 'max:1000'],
        ]);

        $coverImagePath = $request->hasFile('cover_image')
            ? $request->file('cover_image')->store('doopnews', 'public')
            : null;
        unset($validated['cover_image']);

        $post = DoopNewsPost::create([
            ...$validated,
            'cover_image_path' => $coverImagePath,
            'author_id' => (int) $request->user()->id,
            'status' => $request->user()->isStaff()
                ? DoopNewsPost::STATUS_PUBLISHED
                : DoopNewsPost::STATUS_PENDING,
            'submitted_at' => now(),
            'published_at' => $request->user()->isStaff() ? now() : null,
        ]);

        if ($post->status === DoopNewsPost::STATUS_PUBLISHED) {
            CacheVersion::bump('home');
        }

        return redirect()
            ->route('doopnews.index')
            ->with('message', $request->user()->isStaff() ? 'DOOPNEWS_PUBLISHED' : 'DOOPNEWS_SUBMITTED_FOR_REVIEW');
    }

    public function update(Request $request, DoopNewsPost $post): RedirectResponse
    {
        abort_unless((int) $post->author_id === (int) $request->user()->id || $request->user()->isStaff(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'in:'.implode(',', [
                DoopNewsPost::CATEGORY_ANNOUNCEMENT,
                DoopNewsPost::CATEGORY_EVENT,
                DoopNewsPost::CATEGORY_CLASS,
                DoopNewsPost::CATEGORY_QUEST,
                DoopNewsPost::CATEGORY_COMMUNITY,
            ])],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'action_label' => ['nullable', 'string', 'max:80'],
            'action_url' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('cover_image')) {
            if ($post->cover_image_path && Storage::disk('public')->exists($post->cover_image_path)) {
                Storage::disk('public')->delete($post->cover_image_path);
            }

            $validated['cover_image_path'] = $request->file('cover_image')->store('doopnews', 'public');
        }
        unset($validated['cover_image']);

        $post->fill($validated);
        $post->status = $request->user()->isStaff()
            ? DoopNewsPost::STATUS_PUBLISHED
            : DoopNewsPost::STATUS_PENDING;
        $post->submitted_at = now();
        $post->reviewer_id = $request->user()->isStaff() ? (int) $request->user()->id : null;
        $post->reviewed_at = $request->user()->isStaff() ? now() : null;
        $post->published_at = $request->user()->isStaff() ? ($post->published_at ?: now()) : null;
        $post->rejection_reason = null;
        $post->save();

        if ($post->status === DoopNewsPost::STATUS_PUBLISHED) {
            CacheVersion::bump('home');
        }

        return back()->with('message', $request->user()->isStaff() ? 'DOOPNEWS_UPDATED' : 'DOOPNEWS_RESUBMITTED_FOR_REVIEW');
    }

    public function destroy(Request $request, DoopNewsPost $post): RedirectResponse
    {
        abort_unless((int) $post->author_id === (int) $request->user()->id || $request->user()->isStaff(), 403);

        $wasPublished = $post->status === DoopNewsPost::STATUS_PUBLISHED;
        $post->delete();

        if ($wasPublished) {
            CacheVersion::bump('home');
        }

        return back()->with('message', 'DOOPNEWS_DELETED');
    }

    private function isDoopNewsNewForUser(DoopNewsPost $post, array $seenPostIdSet): bool
    {
        $postId = (int) $post->id;
        if ($postId <= 0 || isset($seenPostIdSet[$postId])) {
            return false;
        }

        return $post->published_at
            && $post->published_at->greaterThanOrEqualTo(now()->subDays(30));
    }
}
