<?php

namespace App\Listeners;

use App\Events\JoinGroupRequested;
use App\Models\User;
use App\Notifications\JoinGroupRequestNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

class SendJoinGroupRequestNotification
{
    public function handle(JoinGroupRequested $event): void
    {
        $joinRequest = $event->joinRequest->loadMissing([
            'studyGroup:id,uuid,name',
        ]);

        $group = $joinRequest->studyGroup;
        if (! $group || (int) ($joinRequest->id ?? 0) <= 0) {
            return;
        }

        $groupAdminRecipients = $group->users()
            ->wherePivotIn('role', ['leader', 'admin'])
            ->where('users.id', '!=', (int) $joinRequest->user_id)
            ->select('users.id', 'users.name', 'users.username', 'users.email')
            ->get();

        // Super admin / admin global wajib menerima semua request join group.
        $globalSuperAdminRecipients = User::query()
            ->whereIn('role', User::adminRoles())
            ->where('id', '!=', (int) $joinRequest->user_id)
            ->select('id', 'name', 'username', 'email')
            ->get();

        $recipients = $groupAdminRecipients
            ->concat($globalSuperAdminRecipients)
            ->unique(fn (User $user) => (int) $user->id)
            ->values();

        $recipientIds = $recipients
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $alreadyNotifiedRecipientIds = DatabaseNotification::query()
            ->where('type', JoinGroupRequestNotification::class)
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $recipientIds->all())
            ->where('data->resource->type', 'study_group_join_request')
            ->where('data->resource->id', (int) $joinRequest->id)
            ->pluck('notifiable_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $recipientsToNotify = $recipients
            ->reject(fn (User $user) => in_array((int) $user->id, $alreadyNotifiedRecipientIds, true))
            ->values();

        if ($recipientsToNotify->isEmpty()) {
            return;
        }

        Notification::send($recipientsToNotify, new JoinGroupRequestNotification($joinRequest));
    }
}
