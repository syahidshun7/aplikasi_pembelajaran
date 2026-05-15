<?php

namespace App\Http\Controllers;

use App\Models\Creation;
use App\Models\CreationCollaborator;
use App\Models\CreationCollaborationRequest;
use App\Models\DoopLabTodo;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DoopLabDashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);

        if (! $user || ! $user->canAccessDoopLab()) {
            return redirect()
                ->route('dooplab.index')
                ->with('message', 'ACCESS_DENIED: DOOPLAB_DASHBOARD_PREMIUM_ONLY');
        }

        $ownedCreationsQuery = Creation::query()->where('user_id', $userId);

        $collaboratingCreationIds = CreationCollaborator::query()
            ->where('user_id', $userId)
            ->pluck('creation_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $myCreationsQuery = Creation::query()
            ->where(function ($query) use ($userId, $collaboratingCreationIds) {
                $query->where('user_id', $userId);

                if (! empty($collaboratingCreationIds)) {
                    $query->orWhereIn('id', $collaboratingCreationIds);
                }
            });

        $myCreationStats = [
            'total' => (clone $myCreationsQuery)->count(),
            'in_progress' => (clone $myCreationsQuery)->whereIn('status', ['crafting', 'refining'])->count(),
            'finished' => (clone $myCreationsQuery)->where('status', 'finished')->count(),
            'published' => (clone $myCreationsQuery)->where('publication_status', 'publish')->count(),
            'open_for_collab' => (clone $ownedCreationsQuery)->where('is_open_for_collaboration', true)->count(),
        ];

        $recentExperiments = (clone $myCreationsQuery)
            ->with(['user:id,name,username'])
            ->latest('updated_at')
            ->limit(6)
            ->get([
                'id',
                'title',
                'status',
                'publication_status',
                'is_open_for_collaboration',
                'updated_at',
                'user_id',
            ])
            ->map(fn (Creation $creation) => [
                'id' => (int) $creation->id,
                'title' => (string) ($creation->title ?? 'Untitled Experiment'),
                'status' => (string) ($creation->status ?? 'crafting'),
                'publication_status' => (string) ($creation->publication_status ?? 'draft'),
                'is_open_for_collaboration' => (bool) $creation->is_open_for_collaboration,
                'updated_at' => optional($creation->updated_at)->toIso8601String(),
                'owner' => [
                    'id' => (int) ($creation->user?->id ?? 0),
                    'name' => (string) ($creation->user?->name ?? ''),
                    'username' => (string) ($creation->user?->username ?? ''),
                ],
            ])
            ->values()
            ->all();

        $incomingCollaborationRequests = CreationCollaborationRequest::query()
            ->where('status', CreationCollaborationRequest::STATUS_PENDING)
            ->whereHas('creation', fn ($query) => $query->where('user_id', $userId))
            ->with([
                'requester:id,name,username,profile_photo',
                'creation:id,title',
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CreationCollaborationRequest $item) => [
                'id' => (int) $item->id,
                'creation_title' => (string) ($item->creation?->title ?? ''),
                'requester_name' => (string) ($item->requester?->name ?? ''),
                'requester_username' => (string) ($item->requester?->username ?? ''),
                'requested_role' => (string) ($item->requested_role ?? ''),
                'message' => (string) ($item->message ?? ''),
                'created_at' => optional($item->created_at)->toIso8601String(),
            ])
            ->values()
            ->all();

        $outgoingCollaborationRequests = CreationCollaborationRequest::query()
            ->where('requester_id', $userId)
            ->where('status', CreationCollaborationRequest::STATUS_PENDING)
            ->with([
                'creation:id,title,user_id',
                'creation.user:id,name,username',
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CreationCollaborationRequest $item) => [
                'id' => (int) $item->id,
                'creation_title' => (string) ($item->creation?->title ?? ''),
                'owner_name' => (string) ($item->creation?->user?->name ?? ''),
                'owner_username' => (string) ($item->creation?->user?->username ?? ''),
                'requested_role' => (string) ($item->requested_role ?? ''),
                'created_at' => optional($item->created_at)->toIso8601String(),
            ])
            ->values()
            ->all();

        $mentors = User::query()
            ->where('role', User::ROLE_MENTOR)
            ->with([
                'detailUser:id,user_id,experience,location,skills',
                'job:id,name',
            ])
            ->latest('id')
            ->limit(8)
            ->get([
                'id',
                'name',
                'username',
                'profile_photo',
                'job_id',
            ])
            ->map(fn (User $mentor) => [
                'id' => (int) $mentor->id,
                'name' => (string) ($mentor->name ?? ''),
                'username' => (string) ($mentor->username ?? ''),
                'profile_photo' => (string) ($mentor->profile_photo ?? ''),
                'job_name' => (string) ($mentor->job?->name ?? ''),
                'experience' => (string) ($mentor->detailUser?->experience ?? ''),
                'location' => (string) ($mentor->detailUser?->location ?? ''),
            ])
            ->values()
            ->all();

        $isMentor = $user->isMentor();

        $todoItems = DoopLabTodo::query()
            ->with([
                'owner:id,name,username',
                'mentor:id,name,username',
                'creation:id,title,slug,status,progress',
                'reviewedBy:id,name,username',
                'notes' => fn ($query) => $query
                    ->with('author:id,name,username,role,profile_photo')
                    ->latest('created_at')
                    ->limit(20),
            ])
            ->where(function ($query) use ($userId, $isMentor) {
                $query->where('owner_user_id', $userId);

                if ($isMentor) {
                    $query->orWhere('mentor_user_id', $userId);
                }
            })
            ->orderBy('is_completed')
            ->latest('created_at')
            ->limit(80)
            ->get()
            ->map(function (DoopLabTodo $todo) use ($user) {
                return [
                    'id' => (int) $todo->id,
                    'uuid' => (string) $todo->uuid,
                    'title' => (string) ($todo->title ?? ''),
                    'description' => (string) ($todo->description ?? ''),
                    'start_at' => $todo->start_at?->toIso8601String(),
                    'deadline' => $todo->deadline?->toIso8601String(),
                    'notify_deadline_email' => (bool) $todo->notify_deadline_email,
                    'assignment_mode' => (string) ($todo->assignment_mode ?? DoopLabTodo::MODE_SELF),
                    'milestone_type' => (string) ($todo->milestone_type ?? DoopLabTodo::MILESTONE_TASK),
                    'workflow_status' => (string) ($todo->workflow_status ?? DoopLabTodo::STATUS_TODO),
                    'is_completed' => (bool) $todo->is_completed,
                    'completed_at' => $todo->completed_at?->toIso8601String(),
                    'created_at' => $todo->created_at?->toIso8601String(),
                    'review_requested_at' => $todo->review_requested_at?->toIso8601String(),
                    'reviewed_at' => $todo->reviewed_at?->toIso8601String(),
                    'review_note' => (string) ($todo->review_note ?? ''),
                    'owner' => [
                        'id' => (int) ($todo->owner?->id ?? 0),
                        'name' => (string) ($todo->owner?->name ?? ''),
                        'username' => (string) ($todo->owner?->username ?? ''),
                    ],
                    'mentor' => [
                        'id' => (int) ($todo->mentor?->id ?? 0),
                        'name' => (string) ($todo->mentor?->name ?? ''),
                        'username' => (string) ($todo->mentor?->username ?? ''),
                    ],
                    'creation' => [
                        'id' => (int) ($todo->creation?->id ?? 0),
                        'title' => (string) ($todo->creation?->title ?? ''),
                        'slug' => (string) ($todo->creation?->slug ?? ''),
                        'status' => (string) ($todo->creation?->status ?? ''),
                        'progress' => (int) ($todo->creation?->progress ?? 0),
                    ],
                    'reviewed_by' => [
                        'id' => (int) ($todo->reviewedBy?->id ?? 0),
                        'name' => (string) ($todo->reviewedBy?->name ?? ''),
                        'username' => (string) ($todo->reviewedBy?->username ?? ''),
                    ],
                    'can_toggle' => $todo->canToggleBy($user),
                    'can_edit' => $todo->canEditBy($user),
                    'can_delete' => $todo->canDeleteBy($user),
                    'can_add_note' => $todo->canCommentBy($user),
                    'is_mentor_assigned' => $todo->isMentorAssigned(),
                    'can_submit_review' => $todo->canSubmitCheckpointBy($user),
                    'can_review' => $todo->canReviewCheckpointBy($user),
                    'notes' => $todo->notes
                        ->map(fn ($note) => [
                            'id' => (int) $note->id,
                            'note' => (string) ($note->note ?? ''),
                            'image_path' => (string) ($note->image_path ?? ''),
                            'image_url' => $note->image_path ? asset('storage/'.ltrim((string) $note->image_path, '/')) : null,
                            'created_at' => $note->created_at?->toIso8601String(),
                            'author' => [
                                'id' => (int) ($note->author?->id ?? 0),
                                'name' => (string) ($note->author?->name ?? ''),
                                'username' => (string) ($note->author?->username ?? ''),
                                'role' => (string) ($note->author?->role ?? ''),
                                'profile_photo' => (string) ($note->author?->profile_photo ?? ''),
                            ],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $assignableUsers = $isMentor
            ? User::query()
                ->whereNotIn('role', User::staffRoles())
                ->orderBy('name')
                ->limit(250)
                ->get(['id', 'name', 'username', 'role'])
                ->map(fn (User $item) => [
                    'id' => (int) $item->id,
                    'name' => (string) ($item->name ?? ''),
                    'username' => (string) ($item->username ?? ''),
                    'role' => (string) ($item->role ?? ''),
                ])
                ->values()
                ->all()
            : [];


        $researchWorkspaces = (clone $myCreationsQuery)
            ->latest('updated_at')
            ->limit(40)
            ->get(['id', 'slug', 'title', 'status', 'progress', 'updated_at'])
            ->map(fn (Creation $creation) => [
                'id' => (int) $creation->id,
                'slug' => (string) ($creation->slug ?? ''),
                'title' => (string) ($creation->title ?? 'Untitled Workspace'),
                'status' => (string) ($creation->status ?? 'crafting'),
                'progress' => (int) ($creation->progress ?? 0),
                'updated_at' => $creation->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('DoopLab/Dashboard', [
            'overview' => [
                'system_core' => 'OFFLINE',
                'total_member' => (int) User::query()->where('role', User::ROLE_STUDENT)->count(),
                'total_mentor' => (int) User::query()->where('role', User::ROLE_MENTOR)->count(),
                'total_experiments' => (int) Creation::query()->count(),
                'incoming_review_queue' => (int) count($incomingCollaborationRequests),
            ],
            'my_creation_stats' => $myCreationStats,
            'recent_experiments' => $recentExperiments,
            'collaboration' => [
                'incoming_pending' => (int) count($incomingCollaborationRequests),
                'outgoing_pending' => (int) count($outgoingCollaborationRequests),
                'incoming_items' => $incomingCollaborationRequests,
                'outgoing_items' => $outgoingCollaborationRequests,
            ],
            'mentors' => $mentors,
            'todos' => $todoItems,
            'todo_permissions' => [
                'can_create_mentor' => $isMentor,
            ],
            'todo_assignable_users' => $assignableUsers,
            'research_workspaces' => $researchWorkspaces,
        ]);
    }
}
