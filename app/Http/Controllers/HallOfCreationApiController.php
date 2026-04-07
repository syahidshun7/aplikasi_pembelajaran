<?php

namespace App\Http\Controllers;

use App\Models\Creation;
use App\Models\CreationCollaborationRequest;
use App\Models\CreationCollaborator;
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
                    'collaborators.user:id,name,username,profile_photo',
                ])
                ->withCount(['appreciations', 'insights', 'photos', 'collaborators'])
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
        $userId = (int) ($request->user()?->id ?? 0);
        $this->ensureCanView($creation, $userId);

        $creation->load('user:id,name,username,profile_photo')
            ->load([
                'photos:id,creation_id,path,sort_order',
                'collaborators.user:id,name,username,profile_photo',
            ])
            ->loadCount(['appreciations', 'insights', 'photos', 'collaborators']);

        $isAppreciated = false;

        if ($userId > 0) {
            $isAppreciated = CreationAppreciation::query()
                ->where('user_id', $userId)
                ->where('creation_id', (int) $creation->id)
                ->exists();
        }

        $viewerRequest = $userId > 0
            ? CreationCollaborationRequest::query()
                ->where('creation_id', (int) $creation->id)
                ->where('requester_id', $userId)
                ->latest('id')
                ->first()
            : null;

        $pendingRequests = $creation->canManageCollaboration($userId)
            ? CreationCollaborationRequest::query()
                ->where('creation_id', (int) $creation->id)
                ->where('status', CreationCollaborationRequest::STATUS_PENDING)
                ->with('requester:id,name,username,profile_photo')
                ->latest()
                ->get()
                ->map(fn (CreationCollaborationRequest $item) => $this->transformRequest($item))
                ->values()
                ->all()
            : [];

        return response()->json([
            'data' => [
                ...$this->transformCard($creation),
                'is_appreciated' => $isAppreciated,
                'description' => (string) $creation->description,
                'link' => (string) ($creation->link ?? ''),
                'is_public' => (bool) $creation->is_public,
                'is_open_for_collaboration' => (bool) $creation->is_open_for_collaboration,
                'can_edit' => $creation->canEdit($userId),
                'can_manage_collaboration' => $creation->canManageCollaboration($userId),
                'viewer_role' => $creation->collaboratorRoleFor($userId),
                'viewer_collaboration_request_status' => (string) ($viewerRequest?->status ?? ''),
                'viewer_collaboration_request_id' => $viewerRequest?->id ? (int) $viewerRequest->id : null,
                'pending_collaboration_requests' => $pendingRequests,
            ],
        ]);
    }

    private function ensureCanView(Creation $creation, int $userId): void
    {
        abort_unless($creation->canView($userId), 404, 'CREATION_NOT_FOUND');
    }

    private function transformCard(Creation $creation): array
    {
        $team = $this->transformTeam($creation);

        return [
            'id' => (int) $creation->id,
            'user_id' => (int) $creation->user_id,
            'title' => (string) $creation->title,
            'description' => (string) $creation->description,
            'content' => (string) ($creation->content ?? ''),
            'link' => (string) ($creation->link ?? ''),
            'category' => $creation->category ? (string) $creation->category : null,
            'category_id' => $creation->category_id ? (int) $creation->category_id : null,
            'tags' => collect($creation->tags ?? [])->map(fn ($tag) => (string) $tag)->values()->all(),
            'featured_image' => (string) ($creation->featured_image ?? ''),
            'publication_status' => (string) ($creation->publication_status ?? ((bool) $creation->is_public ? 'publish' : 'draft')),
            'status' => (string) $creation->status,
            'progress' => (int) ($creation->progress ?? 0),
            'is_public' => (bool) $creation->is_public,
            'is_open_for_collaboration' => (bool) $creation->is_open_for_collaboration,
            'appreciations_count' => (int) ($creation->appreciations_count ?? 0),
            'insights_count' => (int) ($creation->insights_count ?? 0),
            'photos_count' => (int) ($creation->photos_count ?? $creation->photos->count()),
            'collaborators_count' => (int) ($creation->collaborators_count ?? $creation->collaborators->count()),
            'team_size' => (int) count($team),
            'thumbnail_url' => (string) ($creation->photos->first()?->url ?? ($creation->featured_image ?? '')),
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
            'team' => $team,
            'created_at' => $creation->created_at?->toISOString(),
            'updated_at' => $creation->updated_at?->toISOString(),
        ];
    }

    private function transformTeam(Creation $creation): array
    {
        $owner = [
            'id' => (int) ($creation->user?->id ?? 0),
            'name' => (string) ($creation->user?->name ?? ''),
            'username' => (string) ($creation->user?->username ?? ''),
            'profile_photo' => (string) ($creation->user?->profile_photo ?? ''),
            'role' => Creation::COLLABORATOR_ROLE_OWNER,
            'is_owner' => true,
        ];

        $collaborators = $creation->collaborators
            ->map(fn (CreationCollaborator $member) => [
                'id' => (int) ($member->user?->id ?? 0),
                'name' => (string) ($member->user?->name ?? ''),
                'username' => (string) ($member->user?->username ?? ''),
                'profile_photo' => (string) ($member->user?->profile_photo ?? ''),
                'role' => (string) $member->role,
                'is_owner' => false,
            ])
            ->values()
            ->all();

        return [$owner, ...$collaborators];
    }

    private function transformRequest(CreationCollaborationRequest $request): array
    {
        return [
            'id' => (int) $request->id,
            'requester_id' => (int) $request->requester_id,
            'requested_role' => (string) ($request->requested_role ?: CreationCollaborator::ROLE_EDITOR),
            'message' => (string) ($request->message ?? ''),
            'status' => (string) $request->status,
            'created_at' => $request->created_at?->toISOString(),
            'requester' => [
                'id' => (int) ($request->requester?->id ?? 0),
                'name' => (string) ($request->requester?->name ?? ''),
                'username' => (string) ($request->requester?->username ?? ''),
                'profile_photo' => (string) ($request->requester?->profile_photo ?? ''),
            ],
        ];
    }
}
