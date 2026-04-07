<?php

namespace App\Http\Controllers;

use App\Http\Requests\Creations\StoreCreationInsightRequest;
use App\Models\Creation;
use App\Models\CreationAppreciation;
use App\Models\CreationInsight;
use App\Services\LmsNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreationInteractionController extends Controller
{
    public function appreciate(Request $request, Creation $creation, LmsNotificationService $notifications): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanInteract($creation, (int) $user->id);

        $appreciation = CreationAppreciation::query()->firstOrCreate([
            'user_id' => (int) $user->id,
            'creation_id' => (int) $creation->id,
        ]);

        if ($appreciation->wasRecentlyCreated) {
            $notifications->notifyCreationAppreciated($creation, $user);
        }

        return response()->json([
            'appreciated' => true,
            'appreciations_count' => $this->appreciationCount($creation),
        ]);
    }

    public function removeAppreciation(Request $request, Creation $creation): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanInteract($creation, (int) $user->id);

        CreationAppreciation::query()
            ->where('user_id', (int) $user->id)
            ->where('creation_id', (int) $creation->id)
            ->delete();

        return response()->json([
            'appreciated' => false,
            'appreciations_count' => $this->appreciationCount($creation),
        ]);
    }

    public function storeInsight(
        StoreCreationInsightRequest $request,
        Creation $creation,
        LmsNotificationService $notifications
    ): JsonResponse {
        $user = $request->user();
        $this->ensureCanInteract($creation, (int) $user->id);

        $validated = $request->validated();
        $content = trim((string) ($validated['content'] ?? ''));
        $parentId = (int) ($validated['parent_id'] ?? 0);

        if ($content === '') {
            return response()->json([
                'message' => 'Insight content is required.',
                'errors' => [
                    'content' => ['Insight content is required.'],
                ],
            ], 422);
        }

        $parentInsight = null;
        if ($parentId > 0) {
            $parentInsight = CreationInsight::query()
                ->where('id', $parentId)
                ->where('creation_id', (int) $creation->id)
                ->first();

            if (! $parentInsight) {
                return response()->json([
                    'message' => 'Parent insight is invalid.',
                    'errors' => [
                        'parent_id' => ['Parent insight does not belong to this creation.'],
                    ],
                ], 422);
            }

            if (! is_null($parentInsight->parent_id)) {
                return response()->json([
                    'message' => 'Only one-level replies are supported.',
                    'errors' => [
                        'parent_id' => ['Nested reply depth is limited to one level.'],
                    ],
                ], 422);
            }
        }

        $insight = CreationInsight::query()->create([
            'user_id' => (int) $user->id,
            'creation_id' => (int) $creation->id,
            'parent_id' => $parentInsight?->id,
            'content' => $content,
        ]);

        $insight->load('user:id,name,username,profile_photo');
        $notifications->notifyCreationInsightAdded($insight);

        return response()->json([
            'message' => 'Insight posted successfully.',
            'data' => $this->transformInsight($insight),
        ], 201);
    }

    public function insights(Request $request, Creation $creation): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->ensureCanView($creation, (int) ($request->user()?->id ?? 0));

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);

        $paginator = CreationInsight::query()
            ->where('creation_id', (int) $creation->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,username,profile_photo',
                'replies' => function ($query) {
                    $query->with('user:id,name,username,profile_photo')
                        ->orderBy('created_at');
                },
            ])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (CreationInsight $insight) => $this->transformInsight($insight, true))
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
        ]);
    }

    private function ensureCanInteract(Creation $creation, int $userId): void
    {
        abort_unless($creation->canView($userId), 403, 'CREATION_ACCESS_DENIED');
    }

    private function ensureCanView(Creation $creation, int $userId): void
    {
        abort_unless($creation->canView($userId), 404, 'CREATION_NOT_FOUND');
    }

    private function appreciationCount(Creation $creation): int
    {
        return (int) CreationAppreciation::query()
            ->where('creation_id', (int) $creation->id)
            ->count();
    }

    private function transformInsight(CreationInsight $insight, bool $includeReplies = false): array
    {
        return [
            'id' => (int) $insight->id,
            'creation_id' => (int) $insight->creation_id,
            'parent_id' => $insight->parent_id ? (int) $insight->parent_id : null,
            'content' => (string) $insight->content,
            'user' => [
                'id' => (int) ($insight->user?->id ?? 0),
                'name' => (string) ($insight->user?->name ?? ''),
                'username' => (string) ($insight->user?->username ?? ''),
                'profile_photo' => (string) ($insight->user?->profile_photo ?? ''),
            ],
            'created_at' => $insight->created_at?->toISOString(),
            'updated_at' => $insight->updated_at?->toISOString(),
            'replies' => $includeReplies
                ? $insight->replies->map(fn (CreationInsight $reply) => $this->transformInsight($reply, false))->values()->all()
                : [],
        ];
    }
}
