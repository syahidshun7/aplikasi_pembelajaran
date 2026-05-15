<?php

namespace App\Http\Controllers;

use App\Models\DoopLabTodo;
use App\Models\DoopLabTodoNote;
use App\Models\Creation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DoopLabTodoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_at' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_at'],
            'notify_deadline_email' => ['nullable', 'boolean'],
            'assignment_mode' => ['nullable', Rule::in([DoopLabTodo::MODE_SELF, DoopLabTodo::MODE_MENTOR])],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'creation_id' => ['nullable', 'integer', 'exists:creations,id'],
            'milestone_type' => ['nullable', Rule::in(DoopLabTodo::milestoneOptions())],
            'workflow_status' => ['nullable', Rule::in(DoopLabTodo::workflowOptions())],
        ]);

        $requestedMode = (string) ($validated['assignment_mode'] ?? DoopLabTodo::MODE_SELF);
        $mode = $user->isMentor() ? $requestedMode : DoopLabTodo::MODE_SELF;

        $ownerUserId = (int) $user->id;
        $mentorUserId = null;

        if ($mode === DoopLabTodo::MODE_MENTOR) {
            if ($user->isMentor()) {
                $targetUserId = (int) ($validated['owner_user_id'] ?? 0);
                if ($targetUserId <= 0) {
                    throw ValidationException::withMessages([
                        'owner_user_id' => 'Pilih member tujuan untuk to-do mentor.',
                    ]);
                }

                $targetUser = User::query()->findOrFail($targetUserId);
                if ($targetUser->isStaff() || ! $targetUser->canAccessDoopLab()) {
                    throw ValidationException::withMessages([
                        'owner_user_id' => 'To-do mentor hanya bisa diberikan ke member DoopLab.',
                    ]);
                }

                $ownerUserId = (int) $targetUser->id;
                $mentorUserId = (int) $user->id;

                $creationId = (int) ($validated['creation_id'] ?? 0);
                if ($creationId > 0) {
                    $canAssignToCreation = Creation::query()
                        ->whereKey($creationId)
                        ->where('user_id', $ownerUserId)
                        ->whereHas('collaborators', fn ($query) => $query->where('user_id', (int) $user->id))
                        ->exists();

                    if (! $canAssignToCreation) {
                        throw ValidationException::withMessages([
                            'creation_id' => 'Creation ini belum hire mentor tersebut atau bukan milik target member.',
                        ]);
                    }
                }
            } else {
                abort(403, 'Hanya mentor yang bisa membuat to-do mode mentor.');
            }
        }

        $startAt = ! empty($validated['start_at'])
            ? Carbon::parse((string) $validated['start_at'])
            : null;
        $deadline = ! empty($validated['deadline'])
            ? Carbon::parse((string) $validated['deadline'])
            : null;
        $notifyDeadlineEmail = $deadline !== null
            ? (bool) ($validated['notify_deadline_email'] ?? false)
            : false;
        $workflowStatus = (string) ($validated['workflow_status'] ?? DoopLabTodo::STATUS_TODO);

        DoopLabTodo::query()->create([
            'title' => trim((string) $validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'start_at' => $startAt,
            'deadline' => $deadline,
            'notify_deadline_email' => $notifyDeadlineEmail,
            'deadline_reminded_at' => null,
            'assignment_mode' => $mode,
            'milestone_type' => (string) ($validated['milestone_type'] ?? DoopLabTodo::MILESTONE_TASK),
            'workflow_status' => $workflowStatus,
            'creation_id' => (int) ($validated['creation_id'] ?? 0) ?: null,
            'owner_user_id' => $ownerUserId,
            'mentor_user_id' => $mentorUserId,
            'is_completed' => in_array($workflowStatus, [DoopLabTodo::STATUS_DONE, DoopLabTodo::STATUS_APPROVED], true),
            'completed_at' => in_array($workflowStatus, [DoopLabTodo::STATUS_DONE, DoopLabTodo::STATUS_APPROVED], true) ? now() : null,
            'completed_by_user_id' => in_array($workflowStatus, [DoopLabTodo::STATUS_DONE, DoopLabTodo::STATUS_APPROVED], true) ? (int) $user->id : null,
            'review_requested_at' => $workflowStatus === DoopLabTodo::STATUS_PENDING_REVIEW ? now() : null,
            'reviewed_at' => in_array($workflowStatus, [DoopLabTodo::STATUS_APPROVED, DoopLabTodo::STATUS_REJECTED], true) ? now() : null,
            'reviewed_by_user_id' => in_array($workflowStatus, [DoopLabTodo::STATUS_APPROVED, DoopLabTodo::STATUS_REJECTED], true) ? (int) $user->id : null,
        ]);

        return back()->with('message', 'DOOPLAB_TODO_CREATED');
    }

    public function toggle(Request $request, DoopLabTodo $todo): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($todo->canToggleBy($user), 403);

        $current = (string) ($todo->workflow_status ?: DoopLabTodo::STATUS_TODO);
        $next = in_array($current, [DoopLabTodo::STATUS_DONE, DoopLabTodo::STATUS_APPROVED], true)
            ? DoopLabTodo::STATUS_TODO
            : DoopLabTodo::STATUS_DONE;

        $todo->update([
            'workflow_status' => $next,
            'is_completed' => $next === DoopLabTodo::STATUS_DONE,
            'completed_at' => $next === DoopLabTodo::STATUS_DONE ? now() : null,
            'completed_by_user_id' => $next === DoopLabTodo::STATUS_DONE ? (int) $user->id : null,
            'review_requested_at' => $next === DoopLabTodo::STATUS_DONE ? $todo->review_requested_at : null,
            'reviewed_at' => $next === DoopLabTodo::STATUS_DONE ? $todo->reviewed_at : null,
            'reviewed_by_user_id' => $next === DoopLabTodo::STATUS_DONE ? $todo->reviewed_by_user_id : null,
            'review_note' => $next === DoopLabTodo::STATUS_DONE ? $todo->review_note : null,
        ]);

        return back()->with('message', $next === DoopLabTodo::STATUS_DONE ? 'DOOPLAB_TODO_COMPLETED' : 'DOOPLAB_TODO_REOPENED');
    }

    public function update(Request $request, DoopLabTodo $todo): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($todo->canEditBy($user), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_at' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_at'],
            'notify_deadline_email' => ['nullable', 'boolean'],
            'creation_id' => ['nullable', 'integer', 'exists:creations,id'],
            'mentor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'milestone_type' => ['nullable', Rule::in(DoopLabTodo::milestoneOptions())],
            'workflow_status' => ['nullable', Rule::in(DoopLabTodo::workflowOptions())],
        ]);

        $startAt = ! empty($validated['start_at'])
            ? Carbon::parse((string) $validated['start_at'])
            : null;
        $deadline = ! empty($validated['deadline'])
            ? Carbon::parse((string) $validated['deadline'])
            : null;
        $notifyDeadlineEmail = $deadline !== null
            ? (bool) ($validated['notify_deadline_email'] ?? false)
            : false;
        $workflowStatus = (string) ($validated['workflow_status'] ?? $todo->workflow_status ?? DoopLabTodo::STATUS_TODO);

        $mentorUserId = (int) ($validated['mentor_user_id'] ?? 0);
        if ($mentorUserId > 0) {
            $mentorUser = User::query()->findOrFail($mentorUserId);
            if (! $mentorUser->isMentor()) {
                throw ValidationException::withMessages([
                    'mentor_user_id' => 'User yang dipilih bukan mentor aktif.',
                ]);
            }
        }

        $todo->update([
            'title' => trim((string) $validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'start_at' => $startAt,
            'deadline' => $deadline,
            'notify_deadline_email' => $notifyDeadlineEmail,
            'deadline_reminded_at' => $notifyDeadlineEmail ? $todo->deadline_reminded_at : null,
            'creation_id' => (int) ($validated['creation_id'] ?? 0) ?: null,
            'mentor_user_id' => $mentorUserId > 0 ? $mentorUserId : $todo->mentor_user_id,
            'milestone_type' => (string) ($validated['milestone_type'] ?? $todo->milestone_type ?? DoopLabTodo::MILESTONE_TASK),
            'workflow_status' => $workflowStatus,
            'is_completed' => in_array($workflowStatus, [DoopLabTodo::STATUS_DONE, DoopLabTodo::STATUS_APPROVED], true),
            'completed_at' => in_array($workflowStatus, [DoopLabTodo::STATUS_DONE, DoopLabTodo::STATUS_APPROVED], true) ? ($todo->completed_at ?: now()) : null,
            'completed_by_user_id' => in_array($workflowStatus, [DoopLabTodo::STATUS_DONE, DoopLabTodo::STATUS_APPROVED], true) ? ((int) ($todo->completed_by_user_id ?: $user->id)) : null,
            'review_requested_at' => $workflowStatus === DoopLabTodo::STATUS_PENDING_REVIEW ? ($todo->review_requested_at ?: now()) : ($workflowStatus === DoopLabTodo::STATUS_TODO ? null : $todo->review_requested_at),
            'reviewed_at' => in_array($workflowStatus, [DoopLabTodo::STATUS_APPROVED, DoopLabTodo::STATUS_REJECTED], true) ? ($todo->reviewed_at ?: now()) : null,
            'reviewed_by_user_id' => in_array($workflowStatus, [DoopLabTodo::STATUS_APPROVED, DoopLabTodo::STATUS_REJECTED], true) ? ((int) ($todo->reviewed_by_user_id ?: $user->id)) : null,
            'review_note' => in_array($workflowStatus, [DoopLabTodo::STATUS_APPROVED, DoopLabTodo::STATUS_REJECTED], true) ? $todo->review_note : null,
        ]);

        return back()->with('message', 'DOOPLAB_TODO_UPDATED');
    }

    public function destroy(Request $request, DoopLabTodo $todo): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($todo->canDeleteBy($user), 403);

        $todo->delete();

        return back()->with('message', 'DOOPLAB_TODO_DELETED');
    }

    public function submitForReview(Request $request, DoopLabTodo $todo): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($todo->canSubmitCheckpointBy($user), 403);

        $todo->update([
            'workflow_status' => DoopLabTodo::STATUS_PENDING_REVIEW,
            'review_requested_at' => now(),
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'review_note' => null,
            'is_completed' => false,
            'completed_at' => null,
            'completed_by_user_id' => null,
        ]);

        return back()->with('message', 'DOOPLAB_CHECKPOINT_SUBMITTED');
    }

    public function reviewCheckpoint(Request $request, DoopLabTodo $todo): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($todo->canReviewCheckpointBy($user), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:1200'],
        ]);

        $approved = (string) $validated['decision'] === 'approve';
        $workflowStatus = $approved ? DoopLabTodo::STATUS_APPROVED : DoopLabTodo::STATUS_REJECTED;

        $todo->update([
            'workflow_status' => $workflowStatus,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => (int) $user->id,
            'review_note' => trim((string) ($validated['review_note'] ?? '')) ?: null,
            'is_completed' => $approved,
            'completed_at' => $approved ? now() : null,
            'completed_by_user_id' => $approved ? (int) $user->id : null,
        ]);

        return back()->with('message', $approved ? 'DOOPLAB_CHECKPOINT_APPROVED' : 'DOOPLAB_CHECKPOINT_REJECTED');
    }

    public function storeNote(Request $request, DoopLabTodo $todo): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($todo->canCommentBy($user), 403);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp', 'required_without:note'],
        ]);

        $noteText = trim((string) ($validated['note'] ?? ''));
        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('dooplab/todo-notes', 'public');
        }

        DoopLabTodoNote::query()->create([
            'todo_id' => (int) $todo->id,
            'author_user_id' => (int) $user->id,
            'note' => $noteText !== '' ? $noteText : null,
            'image_path' => $imagePath,
        ]);

        return back()->with('message', 'DOOPLAB_TODO_NOTE_ADDED');
    }
}
