<?php

namespace App\Http\Controllers;

use App\Http\Requests\Creations\StoreCreationCollaborationRequest;
use App\Models\Creation;
use App\Models\CreationCollaborationRequest;
use App\Models\CreationCollaborator;
use App\Models\User;
use App\Services\LmsNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreationCollaborationController extends Controller
{
    public function storeRequest(
        StoreCreationCollaborationRequest $request,
        Creation $creation,
        LmsNotificationService $notifications
    ): JsonResponse {
        $user = $request->user();
        $userId = (int) $user->id;

        abort_unless($creation->canView($userId) || (bool) $creation->is_public, 404, 'CREATION_NOT_FOUND');

        if (! (bool) $creation->is_open_for_collaboration) {
            return response()->json([
                'message' => 'This creation is not open for collaboration.',
            ], 422);
        }

        if ($creation->isOwnedBy($userId) || $creation->isCollaborator($userId)) {
            return response()->json([
                'message' => 'You are already part of this creation team.',
            ], 422);
        }

        $existingPending = CreationCollaborationRequest::query()
            ->where('creation_id', (int) $creation->id)
            ->where('requester_id', $userId)
            ->where('status', CreationCollaborationRequest::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            return response()->json([
                'message' => 'A collaboration request is already pending.',
            ], 422);
        }

        $validated = $request->validated();

        $collaborationRequest = CreationCollaborationRequest::query()->create([
            'creation_id' => (int) $creation->id,
            'requester_id' => $userId,
            'requested_role' => (string) ($validated['requested_role'] ?? CreationCollaborator::ROLE_EDITOR),
            'message' => trim((string) ($validated['message'] ?? '')) ?: null,
            'status' => CreationCollaborationRequest::STATUS_PENDING,
        ]);

        $notifications->notifyCreationCollaborationRequested($collaborationRequest);

        return response()->json([
            'message' => 'Collaboration request sent.',
            'data' => $this->transformRequest($collaborationRequest->loadMissing('requester:id,name,username,profile_photo')),
        ], 201);
    }

    public function approve(
        Request $request,
        Creation $creation,
        CreationCollaborationRequest $collaborationRequest,
        LmsNotificationService $notifications
    ): JsonResponse {
        $owner = $request->user();
        abort_unless($creation->canManageCollaboration((int) $owner->id), 403, 'CREATION_ACCESS_DENIED');
        abort_unless((int) $collaborationRequest->creation_id === (int) $creation->id, 404, 'COLLABORATION_REQUEST_NOT_FOUND');
        abort_unless((string) $collaborationRequest->status === CreationCollaborationRequest::STATUS_PENDING, 422, 'COLLABORATION_REQUEST_NOT_PENDING');

        DB::transaction(function () use ($collaborationRequest, $creation, $owner) {
            CreationCollaborator::query()->updateOrCreate(
                [
                    'creation_id' => (int) $creation->id,
                    'user_id' => (int) $collaborationRequest->requester_id,
                ],
                [
                    'role' => (string) ($collaborationRequest->requested_role ?: CreationCollaborator::ROLE_EDITOR),
                    'added_by' => (int) $owner->id,
                    'joined_at' => now(),
                ]
            );

            $collaborationRequest->update([
                'status' => CreationCollaborationRequest::STATUS_APPROVED,
                'processed_by' => (int) $owner->id,
                'processed_at' => now(),
            ]);
        });

        $collaborationRequest->loadMissing('requester:id,name,username,email');
        $notifications->notifyCreationCollaborationApproved(
            $creation,
            $collaborationRequest->requester,
            $owner,
            (string) ($collaborationRequest->requested_role ?: CreationCollaborator::ROLE_EDITOR)
        );

        return response()->json([
            'message' => 'Collaboration request approved.',
            'data' => $this->transformRequest($collaborationRequest),
        ]);
    }

    public function reject(
        Request $request,
        Creation $creation,
        CreationCollaborationRequest $collaborationRequest,
        LmsNotificationService $notifications
    ): JsonResponse {
        $owner = $request->user();
        abort_unless($creation->canManageCollaboration((int) $owner->id), 403, 'CREATION_ACCESS_DENIED');
        abort_unless((int) $collaborationRequest->creation_id === (int) $creation->id, 404, 'COLLABORATION_REQUEST_NOT_FOUND');
        abort_unless((string) $collaborationRequest->status === CreationCollaborationRequest::STATUS_PENDING, 422, 'COLLABORATION_REQUEST_NOT_PENDING');

        $collaborationRequest->update([
            'status' => CreationCollaborationRequest::STATUS_REJECTED,
            'processed_by' => (int) $owner->id,
            'processed_at' => now(),
        ]);

        $collaborationRequest->loadMissing('requester:id,name,username,email');
        $notifications->notifyCreationCollaborationRejected($creation, $collaborationRequest->requester, $owner);

        return response()->json([
            'message' => 'Collaboration request rejected.',
            'data' => $this->transformRequest($collaborationRequest),
        ]);
    }

    public function withdraw(Request $request, Creation $creation, CreationCollaborationRequest $collaborationRequest): JsonResponse
    {
        $userId = (int) $request->user()->id;
        abort_unless((int) $collaborationRequest->creation_id === (int) $creation->id, 404, 'COLLABORATION_REQUEST_NOT_FOUND');
        abort_unless((int) $collaborationRequest->requester_id === $userId, 403, 'CREATION_ACCESS_DENIED');
        abort_unless((string) $collaborationRequest->status === CreationCollaborationRequest::STATUS_PENDING, 422, 'COLLABORATION_REQUEST_NOT_PENDING');

        $collaborationRequest->update([
            'status' => CreationCollaborationRequest::STATUS_WITHDRAWN,
            'processed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Collaboration request withdrawn.',
        ]);
    }

    public function removeCollaborator(Request $request, Creation $creation, User $user): JsonResponse
    {
        $ownerId = (int) $request->user()->id;
        abort_unless($creation->canManageCollaboration($ownerId), 403, 'CREATION_ACCESS_DENIED');
        abort_if($creation->isOwnedBy((int) $user->id), 422, 'CREATION_OWNER_CANNOT_BE_REMOVED');

        CreationCollaborator::query()
            ->where('creation_id', (int) $creation->id)
            ->where('user_id', (int) $user->id)
            ->delete();

        return response()->json([
            'message' => 'Collaborator removed.',
        ]);
    }

    private function transformRequest(CreationCollaborationRequest $request): array
    {
        return [
            'id' => (int) $request->id,
            'creation_id' => (int) $request->creation_id,
            'requester_id' => (int) $request->requester_id,
            'requested_role' => (string) ($request->requested_role ?: CreationCollaborator::ROLE_EDITOR),
            'message' => (string) ($request->message ?? ''),
            'status' => (string) $request->status,
            'processed_by' => $request->processed_by ? (int) $request->processed_by : null,
            'processed_at' => $request->processed_at?->toISOString(),
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
