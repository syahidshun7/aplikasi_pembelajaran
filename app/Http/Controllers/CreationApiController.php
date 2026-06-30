<?php

namespace App\Http\Controllers;

use App\Http\Requests\Creations\StoreCreationRequest;
use App\Http\Requests\Creations\UpdateCreationRequest;
use App\Models\Creation;
use App\Models\CreationCategory;
use App\Models\CreationCollaborationRequest;
use App\Models\CreationCollaborator;
use App\Models\CreationPhoto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreationApiController extends Controller
{
    private const MENTOR_INVITE_MESSAGE = 'MENTOR_INVITE_FROM_DOOPLAB';
    private const DIRECT_MENTOR_INVITE_MESSAGE = 'DIRECT_MENTOR_INVITE_FROM_DOOPLAB';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:crafting,refining,finished'],
            'scope' => ['nullable', 'in:owned,collaborating,all'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $scope = (string) ($validated['scope'] ?? 'owned');
        $user = $request->user();
        $userId = (int) $user->id;

        $creations = Creation::query()
            ->with([
                'user:id,name,username,profile_photo',
                'photos:id,creation_id,path,sort_order',
                'collaborators.user:id,name,username,profile_photo',
            ])
            ->withCount(['appreciations', 'insights', 'photos', 'collaborators'])
            ->when($scope === 'owned', fn ($query) => $query->where('user_id', $userId))
            ->when($scope === 'collaborating', function ($query) use ($userId) {
                $query->whereHas('collaborators', fn ($collaborators) => $collaborators->where('user_id', $userId));
            })
            ->when($scope === 'all', function ($query) use ($userId) {
                $query->where(function ($inner) use ($userId) {
                    $inner->where('user_id', $userId)
                        ->orWhereHas('collaborators', fn ($collaborators) => $collaborators->where('user_id', $userId));
                });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $creations->setCollection(
            $creations->getCollection()
                ->map(fn (Creation $creation) => $this->serializeCreation($creation, $userId))
        );

        return response()->json($creations);
    }

    public function store(StoreCreationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $content = $this->normalizeContent((string) ($validated['content'] ?? ''));
        $publicationStatus = (string) ($validated['publication_status'] ?? 'publish');
        $payload = collect($validated)
            ->except(['photos', 'remove_photo_ids'])
            ->all();

        $creation = Creation::query()->create([
            ...$payload,
            'user_id' => (int) $request->user()->id,
            'content' => $content,
            'description' => $this->resolveExcerpt($content, (string) ($validated['description'] ?? '')),
            'category' => $this->resolveCategoryLabel($validated['category_id'] ?? null, $validated['category'] ?? null),
            'status' => (string) ($validated['status'] ?? 'finished'),
            'progress' => (int) ($validated['progress'] ?? 100),
            'publication_status' => $publicationStatus,
            'is_public' => $this->resolvePublicFlag(
                array_key_exists('is_public', $validated) ? $validated['is_public'] : null,
                $publicationStatus
            ),
            'is_open_for_review' => (bool) ($validated['is_open_for_review'] ?? false),
        ]);

        $uploadedPhotos = $this->uploadedPhotos($request);
        $this->assertPhotoLimit($creation, collect(), count($uploadedPhotos));
        $this->attachPhotos($creation, $uploadedPhotos);

        $creation->load([
            'user:id,name,username,profile_photo',
            'photos:id,creation_id,path,sort_order',
            'collaborators.user:id,name,username,profile_photo',
        ])->loadCount(['appreciations', 'insights', 'photos', 'collaborators']);

        return response()->json([
            'message' => 'Creation saved successfully.',
            'data' => $this->serializeCreation($creation, (int) $request->user()->id),
        ], 201);
    }

    public function show(Request $request, Creation $creation): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $this->ensureCanView($creation, $userId);

        $creation->load([
            'user:id,name,username,profile_photo',
            'photos:id,creation_id,path,sort_order',
            'collaborators.user:id,name,username,profile_photo',
        ])->loadCount(['appreciations', 'insights', 'photos', 'collaborators']);

        return response()->json([
            'data' => $this->serializeCreation($creation, $userId),
        ]);
    }

    public function update(UpdateCreationRequest $request, Creation $creation): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $this->ensureCanEdit($creation, $userId);

        $validated = $request->validated();
        $content = array_key_exists('content', $validated)
            ? $this->normalizeContent((string) ($validated['content'] ?? ''))
            : null;
        $payload = collect($validated)
            ->except(['photos', 'remove_photo_ids'])
            ->all();

        if (! $creation->isOwnedBy($userId)) {
            unset($payload['is_public'], $payload['is_open_for_collaboration'], $payload['is_open_for_review'], $payload['publication_status']);
        }

        if (! is_null($content)) {
            $payload['content'] = $content;
            $payload['description'] = $this->resolveExcerpt(
                $content,
                array_key_exists('description', $validated) ? (string) ($validated['description'] ?? '') : (string) $creation->description
            );
        } elseif (array_key_exists('description', $validated)) {
            $payload['description'] = $this->resolveExcerpt((string) ($creation->content ?? ''), (string) ($validated['description'] ?? ''));
        }

        if (array_key_exists('category_id', $validated) || array_key_exists('category', $validated)) {
            $payload['category'] = $this->resolveCategoryLabel($validated['category_id'] ?? null, $validated['category'] ?? null);
        }

        if ($creation->isOwnedBy($userId)) {
            if (array_key_exists('publication_status', $validated)) {
                $publicationStatus = (string) ($validated['publication_status'] ?? 'draft');
                $payload['publication_status'] = $publicationStatus;
                $payload['is_public'] = $this->resolvePublicFlag(
                    array_key_exists('is_public', $validated) ? $validated['is_public'] : null,
                    $publicationStatus
                );
            } elseif (array_key_exists('is_public', $validated)) {
                $payload['is_public'] = (bool) $validated['is_public'];
                $payload['publication_status'] = $payload['is_public'] ? 'publish' : 'draft';
            }
        }

        $removePhotoIds = collect($validated['remove_photo_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($removePhotoIds->isNotEmpty()) {
            $belongsToCreationCount = CreationPhoto::query()
                ->where('creation_id', (int) $creation->id)
                ->whereIn('id', $removePhotoIds->all())
                ->count();

            if ($belongsToCreationCount !== $removePhotoIds->count()) {
                throw ValidationException::withMessages([
                    'remove_photo_ids' => ['Ada foto yang tidak cocok dengan creation ini, jadi tidak bisa dihapus.'],
                ]);
            }
        }

        $uploadedPhotos = $this->uploadedPhotos($request);
        $this->assertPhotoLimit($creation, $removePhotoIds, count($uploadedPhotos));

        $creation->fill($payload);
        $creation->save();

        if ($removePhotoIds->isNotEmpty()) {
            $this->removePhotos($creation, $removePhotoIds);
        }

        $this->attachPhotos($creation, $uploadedPhotos);

        $creation->load([
            'user:id,name,username,profile_photo',
            'photos:id,creation_id,path,sort_order',
            'collaborators.user:id,name,username,profile_photo',
        ])->loadCount(['appreciations', 'insights', 'photos', 'collaborators']);

        return response()->json([
            'message' => 'Creation updated successfully.',
            'data' => $this->serializeCreation($creation, $userId),
        ]);
    }

    public function destroy(Request $request, Creation $creation): JsonResponse
    {
        $this->ensureOwner($creation, (int) $request->user()->id);
        $creation->delete();

        return response()->json([
            'message' => 'Creation deleted successfully.',
        ]);
    }

    public function hireMentor(Request $request, Creation $creation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403, 'DOOPLAB_ACCESS_DENIED');

        $ownerId = (int) $user->id;
        $this->ensureOwner($creation, $ownerId);

        $validated = $request->validate([
            'mentor_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $mentor = User::query()->findOrFail((int) $validated['mentor_user_id']);
        if (! $mentor->isMentor()) {
            throw ValidationException::withMessages([
                'mentor_user_id' => ['User yang dipilih bukan mentor.'],
            ]);
        }

        if ((int) $mentor->id === $ownerId) {
            throw ValidationException::withMessages([
                'mentor_user_id' => ['Owner tidak bisa hire diri sendiri sebagai mentor.'],
            ]);
        }

        if ($creation->isCollaborator((int) $mentor->id)) {
            throw ValidationException::withMessages([
                'mentor_user_id' => ['Mentor ini sudah terhubung ke creation.'],
            ]);
        }

        CreationCollaborationRequest::query()->updateOrCreate(
            [
                'creation_id' => (int) $creation->id,
                'requester_id' => (int) $mentor->id,
                'status' => CreationCollaborationRequest::STATUS_PENDING,
            ],
            [
                'requested_role' => CreationCollaborator::ROLE_VIEWER,
                'message' => self::MENTOR_INVITE_MESSAGE,
                'processed_by' => $ownerId,
                'processed_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Invite mentor terkirim. Menunggu accept dari mentor.',
        ]);
    }

    public function hireDirectMentor(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403, 'DOOPLAB_ACCESS_DENIED');

        if ($user->isStaff()) {
            throw ValidationException::withMessages([
                'mentor_user_id' => ['Staff tidak perlu hire mentor.'],
            ]);
        }

        $validated = $request->validate([
            'mentor_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $memberId = (int) $user->id;
        $mentor = User::query()->findOrFail((int) $validated['mentor_user_id']);

        if (! $mentor->isMentor()) {
            throw ValidationException::withMessages([
                'mentor_user_id' => ['User yang dipilih bukan mentor.'],
            ]);
        }

        if ((int) $mentor->id === $memberId) {
            throw ValidationException::withMessages([
                'mentor_user_id' => ['Kamu tidak bisa hire diri sendiri sebagai mentor.'],
            ]);
        }

        $alreadyConnected = CreationCollaborationRequest::query()
            ->whereNull('creation_id')
            ->where('requester_id', (int) $mentor->id)
            ->where('processed_by', $memberId)
            ->where('message', self::DIRECT_MENTOR_INVITE_MESSAGE)
            ->where('status', CreationCollaborationRequest::STATUS_APPROVED)
            ->exists();

        if ($alreadyConnected) {
            throw ValidationException::withMessages([
                'mentor_user_id' => ['Mentor ini sudah terhubung.'],
            ]);
        }

        CreationCollaborationRequest::query()->updateOrCreate(
            [
                'creation_id' => null,
                'requester_id' => (int) $mentor->id,
                'processed_by' => $memberId,
                'message' => self::DIRECT_MENTOR_INVITE_MESSAGE,
                'status' => CreationCollaborationRequest::STATUS_PENDING,
            ],
            [
                'requested_role' => 'mentor',
                'processed_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Invite mentor terkirim. Menunggu accept dari mentor.',
        ]);
    }

    public function acceptMentorInvite(Request $request, CreationCollaborationRequest $collaborationRequest): JsonResponse
    {
        $mentor = $request->user();
        abort_unless($mentor && $mentor->isMentor(), 403, 'MENTOR_ONLY');
        abort_unless((int) $collaborationRequest->requester_id === (int) $mentor->id, 403, 'INVITE_FORBIDDEN');
        abort_unless((string) $collaborationRequest->status === CreationCollaborationRequest::STATUS_PENDING, 422, 'INVITE_NOT_PENDING');

        if ((int) ($collaborationRequest->creation_id ?? 0) > 0) {
            CreationCollaborator::query()->updateOrCreate(
                [
                    'creation_id' => (int) $collaborationRequest->creation_id,
                    'user_id' => (int) $mentor->id,
                ],
                [
                    'role' => (string) ($collaborationRequest->requested_role ?: CreationCollaborator::ROLE_VIEWER),
                    'added_by' => (int) ($collaborationRequest->processed_by ?: $collaborationRequest->creation?->user_id),
                    'joined_at' => now(),
                ]
            );
        }

        $updatePayload = [
            'status' => CreationCollaborationRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ];

        if ((int) ($collaborationRequest->creation_id ?? 0) > 0) {
            $updatePayload['processed_by'] = (int) $mentor->id;
        }

        $collaborationRequest->update($updatePayload);

        return response()->json(['message' => 'Invite mentor diterima.']);
    }

    public function rejectMentorInvite(Request $request, CreationCollaborationRequest $collaborationRequest): JsonResponse
    {
        $mentor = $request->user();
        abort_unless($mentor && $mentor->isMentor(), 403, 'MENTOR_ONLY');
        abort_unless((int) $collaborationRequest->requester_id === (int) $mentor->id, 403, 'INVITE_FORBIDDEN');
        abort_unless((string) $collaborationRequest->status === CreationCollaborationRequest::STATUS_PENDING, 422, 'INVITE_NOT_PENDING');

        $updatePayload = [
            'status' => CreationCollaborationRequest::STATUS_REJECTED,
            'processed_at' => now(),
        ];

        if ((int) ($collaborationRequest->creation_id ?? 0) > 0) {
            $updatePayload['processed_by'] = (int) $mentor->id;
        }

        $collaborationRequest->update($updatePayload);

        return response()->json(['message' => 'Invite mentor ditolak.']);
    }

    private function ensureOwner(Creation $creation, int $userId): void
    {
        abort_unless($creation->isOwnedBy($userId), 403, 'CREATION_ACCESS_DENIED');
    }

    private function ensureCanView(Creation $creation, int $userId): void
    {
        abort_unless($creation->canView($userId), 403, 'CREATION_ACCESS_DENIED');
    }

    private function ensureCanEdit(Creation $creation, int $userId): void
    {
        abort_unless($creation->canEdit($userId), 403, 'CREATION_ACCESS_DENIED');
    }

    /**
     * @return UploadedFile[]
     */
    private function uploadedPhotos(Request $request): array
    {
        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            return [];
        }

        return array_values(array_filter(
            $photos,
            fn ($photo) => $photo instanceof UploadedFile
        ));
    }

    private function assertPhotoLimit(Creation $creation, Collection $removePhotoIds, int $newPhotoCount): void
    {
        $currentCount = (int) $creation->photos()->count();
        $removedCount = $removePhotoIds->isEmpty()
            ? 0
            : (int) $creation->photos()->whereIn('id', $removePhotoIds->all())->count();

        $finalCount = $currentCount - $removedCount + $newPhotoCount;

        if ($finalCount > 8) {
            throw ValidationException::withMessages([
                'photos' => ['Jumlah total foto maksimal 8 untuk satu creation.'],
            ]);
        }
    }

    private function removePhotos(Creation $creation, Collection $removePhotoIds): void
    {
        $photos = CreationPhoto::query()
            ->where('creation_id', (int) $creation->id)
            ->whereIn('id', $removePhotoIds->all())
            ->get(['id', 'path']);

        $paths = $photos->pluck('path')
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->values()
            ->all();

        if (! empty($paths)) {
            Storage::disk('public')->delete($paths);
        }

        if ($photos->isNotEmpty()) {
            CreationPhoto::query()
                ->whereIn('id', $photos->pluck('id')->all())
                ->delete();
        }
    }

    /**
     * @param  UploadedFile[]  $photos
     */
    private function attachPhotos(Creation $creation, array $photos): void
    {
        if (empty($photos)) {
            return;
        }

        $nextSortOrder = (int) $creation->photos()->max('sort_order');

        foreach ($photos as $photo) {
            $nextSortOrder++;
            $path = $photo->store('creations', 'public');

            CreationPhoto::query()->create([
                'creation_id' => (int) $creation->id,
                'path' => $path,
                'sort_order' => $nextSortOrder,
            ]);
        }
    }

    private function serializeCreation(Creation $creation, int $viewerId): array
    {
        $photos = $creation->photos
            ->map(fn (CreationPhoto $photo) => [
                'id' => (int) $photo->id,
                'url' => (string) $photo->url,
                'sort_order' => (int) ($photo->sort_order ?? 0),
            ])
            ->values()
            ->all();
        $team = $this->serializeTeam($creation);
        $viewerRole = $creation->collaboratorRoleFor($viewerId);

        return [
            'id' => (int) $creation->id,
            'slug' => (string) ($creation->slug ?? ''),
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
            'is_open_for_review' => (bool) ($creation->is_open_for_review ?? false),
            'review_status' => (string) ($creation->review_status ?? 'none'),
            'assigned_reviewer_id' => $creation->assigned_reviewer_id ? (int) $creation->assigned_reviewer_id : null,
            'assigned_rubric_id' => $creation->assigned_rubric_id ? (int) $creation->assigned_rubric_id : null,
            'appreciations_count' => (int) ($creation->appreciations_count ?? 0),
            'insights_count' => (int) ($creation->insights_count ?? 0),
            'photos_count' => (int) ($creation->photos_count ?? count($photos)),
            'collaborators_count' => (int) ($creation->collaborators_count ?? $creation->collaborators->count()),
            'team_size' => (int) count($team),
            'thumbnail_url' => (string) ($photos[0]['url'] ?? ($creation->featured_image ?? '')),
            'photos' => $photos,
            'creator' => [
                'id' => (int) ($creation->user?->id ?? 0),
                'name' => (string) ($creation->user?->name ?? ''),
                'username' => (string) ($creation->user?->username ?? ''),
                'profile_photo' => (string) ($creation->user?->profile_photo ?? ''),
            ],
            'team' => $team,
            'viewer_role' => $viewerRole,
            'ownership_type' => $creation->isOwnedBy($viewerId) ? 'owner' : ($creation->isCollaborator($viewerId) ? 'collaborator' : 'viewer'),
            'can_edit' => $creation->canEdit($viewerId),
            'can_delete' => $creation->isOwnedBy($viewerId),
            'can_manage_collaboration' => $creation->canManageCollaboration($viewerId),
            'created_at' => $creation->created_at?->toISOString(),
            'updated_at' => $creation->updated_at?->toISOString(),
        ];
    }

    private function normalizeContent(string $content): string
    {
        return trim($content);
    }

    private function resolveExcerpt(string $content, string $fallbackDescription = ''): string
    {
        $normalizedHtml = preg_replace('/<(\/?(p|div|li|ul|ol|blockquote|pre|h[1-6]))\b[^>]*>/iu', ' ', $content);
        $normalizedHtml = preg_replace('/<br\s*\/?>/iu', ' ', (string) $normalizedHtml);
        $contentText = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $normalizedHtml)));

        if ($contentText !== '') {
            return mb_strimwidth($contentText, 0, 280, '...');
        }

        return trim($fallbackDescription);
    }

    private function resolveCategoryLabel(mixed $categoryId, mixed $category): ?string
    {
        $resolvedCategoryId = (int) ($categoryId ?? 0);
        if ($resolvedCategoryId > 0) {
            return CreationCategory::query()->whereKey($resolvedCategoryId)->value('name');
        }

        $categoryName = trim((string) ($category ?? ''));
        return $categoryName !== '' ? $categoryName : null;
    }

    private function resolvePublicFlag(mixed $isPublic, string $publicationStatus): bool
    {
        if (! is_null($isPublic)) {
            return (bool) $isPublic;
        }

        return $publicationStatus === 'publish';
    }

    private function serializeTeam(Creation $creation): array
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
}
