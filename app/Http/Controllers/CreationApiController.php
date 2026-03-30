<?php

namespace App\Http\Controllers;

use App\Http\Requests\Creations\StoreCreationRequest;
use App\Http\Requests\Creations\UpdateCreationRequest;
use App\Models\Creation;
use App\Models\CreationPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:crafting,refining,finished'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $user = $request->user();

        $creations = Creation::query()
            ->where('user_id', (int) $user->id)
            ->with([
                'user:id,name,username,profile_photo',
                'photos:id,creation_id,path,sort_order',
            ])
            ->withCount(['appreciations', 'insights', 'photos'])
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
                ->map(fn (Creation $creation) => $this->serializeCreation($creation))
        );

        return response()->json($creations);
    }

    public function store(StoreCreationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $payload = collect($validated)
            ->except(['photos', 'remove_photo_ids'])
            ->all();

        $creation = Creation::query()->create([
            ...$payload,
            'user_id' => (int) $request->user()->id,
        ]);

        $uploadedPhotos = $this->uploadedPhotos($request);
        $this->assertPhotoLimit($creation, collect(), count($uploadedPhotos));
        $this->attachPhotos($creation, $uploadedPhotos);

        $creation->load([
            'user:id,name,username,profile_photo',
            'photos:id,creation_id,path,sort_order',
        ])->loadCount(['appreciations', 'insights', 'photos']);

        return response()->json([
            'message' => 'Creation saved successfully.',
            'data' => $this->serializeCreation($creation),
        ], 201);
    }

    public function show(Request $request, Creation $creation): JsonResponse
    {
        $this->ensureOwner($creation, (int) $request->user()->id);

        $creation->load([
            'user:id,name,username,profile_photo',
            'photos:id,creation_id,path,sort_order',
        ])->loadCount(['appreciations', 'insights', 'photos']);

        return response()->json([
            'data' => $this->serializeCreation($creation),
        ]);
    }

    public function update(UpdateCreationRequest $request, Creation $creation): JsonResponse
    {
        $this->ensureOwner($creation, (int) $request->user()->id);

        $validated = $request->validated();
        $payload = collect($validated)
            ->except(['photos', 'remove_photo_ids'])
            ->all();

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
        ])->loadCount(['appreciations', 'insights', 'photos']);

        return response()->json([
            'message' => 'Creation updated successfully.',
            'data' => $this->serializeCreation($creation),
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

    private function ensureOwner(Creation $creation, int $userId): void
    {
        abort_unless((int) $creation->user_id === $userId, 403, 'CREATION_ACCESS_DENIED');
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

    private function serializeCreation(Creation $creation): array
    {
        $photos = $creation->photos
            ->map(fn (CreationPhoto $photo) => [
                'id' => (int) $photo->id,
                'url' => (string) $photo->url,
                'sort_order' => (int) ($photo->sort_order ?? 0),
            ])
            ->values()
            ->all();

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
            'photos_count' => (int) ($creation->photos_count ?? count($photos)),
            'thumbnail_url' => (string) ($photos[0]['url'] ?? ''),
            'photos' => $photos,
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
