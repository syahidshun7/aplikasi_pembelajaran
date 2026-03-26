<?php

namespace App\Http\Controllers;

use App\Models\Creation;
use App\Models\CreationAppreciation;
use App\Models\CreationPhoto;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class HallOfCreationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:crafting,refining,finished'],
            'sort' => ['nullable', 'in:latest,popular'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $page = (int) ($validated['page'] ?? 1);
        $search = trim((string) ($validated['search'] ?? ''));
        $category = trim((string) ($validated['category'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'latest');
        $cacheVersion = CacheVersion::get('hall_of_creations');
        $userId = (int) ($request->user()?->id ?? 0);

        $cacheKey = sprintf(
            'hall_of_creations.index.v%s.%s',
            $cacheVersion,
            sha1(json_encode([
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'sort' => $sort,
            ]))
        );

        $payload = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($perPage, $page, $search, $category, $status, $sort) {
            $query = Creation::query()
                ->publicVisible()
                ->with([
                    'user:id,name,username,profile_photo',
                    'photos:id,creation_id,path,sort_order',
                ])
                ->withCount(['appreciations', 'insights', 'photos'])
                ->when($search !== '', function ($builder) use ($search) {
                    $builder->where(function ($inner) use ($search) {
                        $inner->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($category !== '', fn ($builder) => $builder->where('category', $category))
                ->when($status !== '', fn ($builder) => $builder->where('status', $status));

            if ($sort === 'popular') {
                $query->orderByDesc('appreciations_count')
                    ->orderByDesc('insights_count')
                    ->latest();
            } else {
                $query->latest();
            }

            /** @var LengthAwarePaginator $paginator */
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => collect($paginator->items())
                    ->map(fn (Creation $creation) => $this->transformCard($creation))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ];
        });

        $creationIds = collect($payload['data'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $appreciatedIds = $userId > 0 && $creationIds->isNotEmpty()
            ? CreationAppreciation::query()
                ->where('user_id', $userId)
                ->whereIn('creation_id', $creationIds->all())
                ->pluck('creation_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $payload['data'] = collect($payload['data'] ?? [])
            ->map(function (array $item) use ($appreciatedIds) {
                $item['is_appreciated'] = in_array((int) ($item['id'] ?? 0), $appreciatedIds, true);

                return $item;
            })
            ->values()
            ->all();

        return response()->json($payload);
    }

    public function show(Request $request, Creation $creation): JsonResponse
    {
        $this->ensureCanView($creation, (int) ($request->user()?->id ?? 0));

        $creation->load('user:id,name,username,profile_photo')
            ->load([
                'photos:id,creation_id,path,sort_order',
            ])
            ->loadCount(['appreciations', 'insights', 'photos']);

        $userId = (int) ($request->user()?->id ?? 0);
        $isAppreciated = false;

        if ($userId > 0) {
            $isAppreciated = CreationAppreciation::query()
                ->where('user_id', $userId)
                ->where('creation_id', (int) $creation->id)
                ->exists();
        }

        return response()->json([
            'data' => [
                ...$this->transformCard($creation),
                'is_appreciated' => $isAppreciated,
                'description' => (string) $creation->description,
                'link' => (string) ($creation->link ?? ''),
                'is_public' => (bool) $creation->is_public,
            ],
        ]);
    }

    private function ensureCanView(Creation $creation, int $userId): void
    {
        $canView = (bool) $creation->is_public || ((int) $creation->user_id === $userId && $userId > 0);
        abort_unless($canView, 404, 'CREATION_NOT_FOUND');
    }

    private function transformCard(Creation $creation): array
    {
        return [
            'id' => (int) $creation->id,
            'user_id' => (int) $creation->user_id,
            'title' => (string) $creation->title,
            'description' => (string) $creation->description,
            'link' => (string) ($creation->link ?? ''),
            'category' => $creation->category ? (string) $creation->category : null,
            'status' => (string) $creation->status,
            'progress' => (int) ($creation->progress ?? 0),
            'is_public' => (bool) $creation->is_public,
            'appreciations_count' => (int) ($creation->appreciations_count ?? 0),
            'insights_count' => (int) ($creation->insights_count ?? 0),
            'photos_count' => (int) ($creation->photos_count ?? $creation->photos->count()),
            'thumbnail_url' => (string) ($creation->photos->first()?->url ?? ''),
            'photos' => $creation->photos
                ->map(fn (CreationPhoto $photo) => [
                    'id' => (int) $photo->id,
                    'url' => (string) $photo->url,
                    'sort_order' => (int) ($photo->sort_order ?? 0),
                ])
                ->values()
                ->all(),
            'creator' => [
                'id' => (int) ($creation->user?->id ?? 0),
                'name' => (string) ($creation->user?->name ?? ''),
                'username' => (string) ($creation->user?->username ?? ''),
                'profile_photo' => (string) ($creation->user?->profile_photo ?? ''),
            ],
            'created_at' => $creation->created_at?->toISOString(),
            'updated_at' => $creation->updated_at?->toISOString(),
        ];
    }
}
