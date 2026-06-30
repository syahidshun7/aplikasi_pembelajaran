<?php

namespace App\Http\Controllers;

use App\Models\DoopLabLogbook;
use App\Models\DoopLabLogbookEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class DoopLabLogbookController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);

        $v = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        DoopLabLogbook::query()->create([
            'owner_user_id' => (int) $user->id,
            'title'         => trim($v['title']),
            'description'   => trim((string) ($v['description'] ?? '')) ?: null,
        ]);

        return back()->with('message', 'DOOPLAB_LOGBOOK_CREATED');
    }

    /**
     * Mentor membuat logbook dan meng-assign ke multiple users dan multiple mentors.
     */
    public function assign(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->isMentor() || $user->isAdmin()), 403, 'LOGBOOK_ASSIGN_MENTOR_ONLY');

        $v = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_ids'  => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'mentor_ids'  => ['nullable', 'array'],
            'mentor_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $logbook = DoopLabLogbook::query()->create([
            'owner_user_id' => (int) $user->id,
            'title'         => trim($v['title']),
            'description'   => trim((string) ($v['description'] ?? '')) ?: null,
        ]);

        $pivotRows = [];
        $now = now();

        foreach (array_unique((array) $v['member_ids']) as $uid) {
            $pivotRows[] = ['logbook_id' => $logbook->id, 'user_id' => (int) $uid, 'role' => 'member', 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_unique((array) ($v['mentor_ids'] ?? [])) as $uid) {
            if (! in_array((int) $uid, array_column($pivotRows, 'user_id'), true)) {
                $pivotRows[] = ['logbook_id' => $logbook->id, 'user_id' => (int) $uid, 'role' => 'mentor', 'created_at' => $now, 'updated_at' => $now];
            }
        }

        // Owner-nya sendiri sebagai mentor jika belum ada
        $ownerInList = collect($pivotRows)->contains('user_id', (int) $user->id);
        if (! $ownerInList) {
            $pivotRows[] = ['logbook_id' => $logbook->id, 'user_id' => (int) $user->id, 'role' => 'mentor', 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('dooplab_logbook_members')->insert($pivotRows);

        return back()->with('message', 'DOOPLAB_LOGBOOK_ASSIGNED');
    }

    public function update(Request $request, DoopLabLogbook $logbook): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($logbook->canEditBy($user), 403);

        $isOwner = (int) $logbook->owner_user_id === (int) $user->id || $user->isAdmin();

        $rules = ['description' => ['nullable', 'string', 'max:1000']];
        if ($isOwner) {
            $rules['title']       = ['required', 'string', 'max:200'];
            $rules['member_ids']  = ['nullable', 'array'];
            $rules['member_ids.*'] = ['integer', 'exists:users,id'];
            $rules['mentor_ids']  = ['nullable', 'array'];
            $rules['mentor_ids.*'] = ['integer', 'exists:users,id'];
        }

        $v = $request->validate($rules);

        $payload = ['description' => trim((string) ($v['description'] ?? '')) ?: null];
        if ($isOwner && isset($v['title'])) {
            $payload['title'] = trim($v['title']);
        }
        $logbook->update($payload);

        // Sync pivot hanya jika owner dan logbook sudah assigned (punya anggota)
        if ($isOwner && ($request->has('member_ids') || $request->has('mentor_ids'))) {
            $now = now();
            $memberIds = array_unique(array_map('intval', (array) ($v['member_ids'] ?? [])));
            $mentorIds = array_unique(array_map('intval', (array) ($v['mentor_ids'] ?? [])));

            // Owner sendiri tetap sebagai mentor
            if (! in_array((int) $user->id, $mentorIds, true)) {
                $mentorIds[] = (int) $user->id;
            }

            $pivotRows = [];
            foreach ($memberIds as $uid) {
                $pivotRows[$uid] = ['role' => 'member', 'created_at' => $now, 'updated_at' => $now];
            }
            foreach ($mentorIds as $uid) {
                if (! isset($pivotRows[$uid])) {
                    $pivotRows[$uid] = ['role' => 'mentor', 'created_at' => $now, 'updated_at' => $now];
                }
            }

            $logbook->membersAll()->sync($pivotRows);
        }

        return back()->with('message', 'DOOPLAB_LOGBOOK_UPDATED');
    }

    public function destroy(Request $request, DoopLabLogbook $logbook): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($logbook->canDeleteBy($user), 403);

        $logbook->delete();

        return back()->with('message', 'DOOPLAB_LOGBOOK_DELETED');
    }

    private function isMentorOfLogbook(DoopLabLogbook $logbook, User $user): bool
    {
        if ($user->isAdmin()) return true;
        if ((int) $logbook->owner_user_id === (int) $user->id) return true;
        return $logbook->mentors()->where('users.id', $user->id)->exists();
    }

    public function storeEntry(Request $request, DoopLabLogbook $logbook): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($logbook->canEditBy($user), 403);

        $v = $request->validate([
            'activity_date' => ['required', 'date'],
            'activity_time' => ['nullable', 'date_format:H:i'],
            'activity'      => ['required', 'string', 'max:500'],
            'purpose'       => ['nullable', 'string', 'max:2000'],
            'result'        => ['nullable', 'string', 'max:2000'],
            'documentation' => ['nullable', 'array', 'max:5'],
            'documentation.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $docPaths = $this->storeDocumentationFiles($request);

        // Mentor/owner langsung approved, member biasa pending
        $status = $this->isMentorOfLogbook($logbook, $user)
            ? DoopLabLogbookEntry::STATUS_APPROVED
            : DoopLabLogbookEntry::STATUS_PENDING;

        DoopLabLogbookEntry::query()->create([
            'logbook_id'         => (int) $logbook->id,
            'activity_date'      => $v['activity_date'],
            'activity_time'      => $v['activity_time'] ?? null,
            'activity'           => trim($v['activity']),
            'purpose'            => trim((string) ($v['purpose'] ?? '')) ?: null,
            'result'             => trim((string) ($v['result'] ?? '')) ?: null,
            'status'             => $status,
            'documentation_path'  => $docPaths[0] ?? null,
            'documentation_paths' => $docPaths ?: null,
        ]);

        return back()->with('message', 'DOOPLAB_LOGBOOK_ENTRY_CREATED');
    }

    public function updateEntry(Request $request, DoopLabLogbook $logbook, DoopLabLogbookEntry $entry): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($logbook->canEditBy($user) && (int) $entry->logbook_id === (int) $logbook->id, 403);

        $v = $request->validate([
            'activity_date' => ['required', 'date'],
            'activity_time' => ['nullable', 'date_format:H:i'],
            'activity'      => ['required', 'string', 'max:500'],
            'purpose'       => ['nullable', 'string', 'max:2000'],
            'result'        => ['nullable', 'string', 'max:2000'],
            'documentation' => ['nullable', 'array', 'max:5'],
            'documentation.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $docPaths = $request->hasFile('documentation')
            ? $this->storeDocumentationFiles($request)
            : ($entry->documentation_paths ?: ($entry->documentation_path ? [$entry->documentation_path] : []));

        $entry->update([
            'activity_date'      => $v['activity_date'],
            'activity_time'      => $v['activity_time'] ?? null,
            'activity'           => trim($v['activity']),
            'purpose'            => trim((string) ($v['purpose'] ?? '')) ?: null,
            'result'             => trim((string) ($v['result'] ?? '')) ?: null,
            'documentation_path'  => $docPaths[0] ?? null,
            'documentation_paths' => $docPaths ?: null,
        ]);

        return back()->with('message', 'DOOPLAB_LOGBOOK_ENTRY_UPDATED');
    }

    public function approveEntry(Request $request, DoopLabLogbook $logbook, DoopLabLogbookEntry $entry): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless((int) $entry->logbook_id === (int) $logbook->id, 404);
        abort_unless($this->isMentorOfLogbook($logbook, $user), 403, 'MENTOR_ONLY');

        $entry->update(['status' => DoopLabLogbookEntry::STATUS_APPROVED]);

        return back()->with('message', 'DOOPLAB_LOGBOOK_ENTRY_APPROVED');
    }

    public function destroyEntry(Request $request, DoopLabLogbook $logbook, DoopLabLogbookEntry $entry): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessDoopLab(), 403);
        abort_unless($logbook->canDeleteBy($user) && (int) $entry->logbook_id === (int) $logbook->id, 403);

        $entry->delete();

        return back()->with('message', 'DOOPLAB_LOGBOOK_ENTRY_DELETED');
    }

    private function storeDocumentationFiles(Request $request): array
    {
        $files = $request->file('documentation', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        return collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->take(5)
            ->map(fn (UploadedFile $file) => $file->store('dooplab/logbooks', 'public'))
            ->values()
            ->all();
    }
}
