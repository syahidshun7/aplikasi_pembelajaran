<?php

namespace App\Notifications;

use App\Models\StudyGroupJoinRequest;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class JoinGroupRequestRejectedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly StudyGroupJoinRequest $joinRequest,
        private readonly ?string $rejectionReason = null,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    private function payload(): array
    {
        $joinRequest = $this->joinRequest->loadMissing([
            'studyGroup:id,uuid,name',
            'user:id,name,username',
        ]);

        $groupName = (string) ($joinRequest->studyGroup?->name ?: 'Study Group');
        $requesterName = (string) ($joinRequest->user?->username ?: $joinRequest->user?->name ?: 'User');
        $reason = trim((string) ($this->rejectionReason ?? ''));
        $reasonSnippet = $reason !== ''
            ? mb_strimwidth($reason, 0, 280, '...')
            : null;

        $message = $reasonSnippet !== null
            ? sprintf(
                'Permintaan join kamu ke group "%s" ditolak. Alasan admin: %s',
                $groupName,
                $reasonSnippet
            )
            : sprintf(
                'Permintaan join kamu ke group "%s" ditolak oleh admin.',
                $groupName
            );

        return $this->buildPayload([
            'type' => 'study_group',
            'category' => 'study_group',
            'event' => 'join_rejected',
            'title' => 'Permintaan Join Ditolak',
            'message' => $message,
            'action_url' => route('groups.index'),
            'action_label' => 'Lihat party',
            'icon' => 'fi-rr-cross-circle',
            'accent' => 'amber',
            'resource' => [
                'type' => 'study_group_join_request',
                'id' => (int) $joinRequest->id,
                'study_group_id' => (int) ($joinRequest->study_group_id ?? 0),
                'study_group_uuid' => (string) ($joinRequest->studyGroup?->uuid ?? ''),
            ],
            'meta' => [
                'requester_name' => $requesterName,
                'group_name' => $groupName,
                'reason' => $reasonSnippet,
                'requested_at' => $joinRequest->created_at?->toISOString(),
                'rejected_at' => now()->toISOString(),
            ],
        ]);
    }
}

