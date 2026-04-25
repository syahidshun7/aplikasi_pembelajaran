<?php

namespace App\Http\Controllers;

use App\Models\DoopLabTodo;
use App\Models\DoopLabTodoNote;
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
        ]);

        $requestedMode = (string) ($validated['assignment_mode'] ?? DoopLabTodo::MODE_SELF);
        $mode = $user->isMentor() ? $requestedMode : DoopLabTodo::MODE_SELF;

        $ownerUserId = (int) $user->id;
        $mentorUserId = null;

        if ($mode === DoopLabTodo::MODE_MENTOR) {
            abort_unless($user->isMentor(), 403);

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

        DoopLabTodo::query()->create([
            'title' => trim((string) $validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'start_at' => $startAt,
            'deadline' => $deadline,
            'notify_deadline_email' => $notifyDeadlineEmail,
            'deadline_reminded_at' => null,
            'assignment_mode' => $mode,
            'owner_user_id' => $ownerUserId,
            'mentor_user_id' => $mentorUserId,
            'is_completed' => false,
            'completed_at' => null,
            'completed_by_user_id' => null,
        ]);

        return back()->with('message', 'DOOPLAB_TODO_CREATED');
    }

    public function toggle(Request $request, DoopLabTodo $todo): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($todo->canToggleBy($user), 403);

        $isCompleted = (bool) $todo->is_completed;
        $nextCompleted = ! $isCompleted;

        $todo->update([
            'is_completed' => $nextCompleted,
            'completed_at' => $nextCompleted ? now() : null,
            'completed_by_user_id' => $nextCompleted ? (int) $user->id : null,
        ]);

        return back()->with('message', $nextCompleted ? 'DOOPLAB_TODO_COMPLETED' : 'DOOPLAB_TODO_REOPENED');
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

        $currentDeadlineIso = $todo->deadline?->toISOString();
        $incomingDeadlineIso = $deadline?->toISOString();
        $deadlineChanged = $currentDeadlineIso !== $incomingDeadlineIso;

        $todo->update([
            'title' => trim((string) $validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'start_at' => $startAt,
            'deadline' => $deadline,
            'notify_deadline_email' => $notifyDeadlineEmail,
            'deadline_reminded_at' => $deadlineChanged ? null : $todo->deadline_reminded_at,
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

    public function storeNote(Request $request, DoopLabTodo $todo): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($todo->canCommentBy($user), 403);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'required_without:note'],
        ]);

        $noteText = trim((string) ($validated['note'] ?? ''));
        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('dooplab/todo-notes', 'public');
        }

        if ($noteText === '' && $imagePath === null) {
            throw ValidationException::withMessages([
                'note' => 'Isi catatan atau lampirkan gambar bukti.',
            ]);
        }

        DoopLabTodoNote::query()->create([
            'todo_id' => (int) $todo->id,
            'author_user_id' => (int) $user->id,
            'note' => $noteText !== '' ? $noteText : null,
            'image_path' => $imagePath,
        ]);

        return back()->with('message', 'DOOPLAB_TODO_NOTE_CREATED');
    }
}
