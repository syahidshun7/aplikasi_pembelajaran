<?php

namespace App\Notifications;

use App\Models\StudyGroupJoinRequest;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class JoinGroupRequestNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly StudyGroupJoinRequest $joinRequest,
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
            'user:id,name,username',
            'studyGroup:id,uuid,name',
        ]);

        $requesterName = (string) ($joinRequest->user?->username ?: $joinRequest->user?->name ?: 'User');
        $groupName = (string) ($joinRequest->studyGroup?->name ?: 'Study Group');
        $requestedAt = $joinRequest->created_at?->toISOString() ?? now()->toISOString();
        $reason = trim((string) ($joinRequest->reason ?? ''));
        $reasonSnippet = $reason !== ''
            ? mb_strimwidth($reason, 0, 200, '...')
            : '-';

        return $this->buildPayload([
            'type' => 'study_group',
            'category' => 'study_group',
            'event' => 'join_requested',
            'title' => 'Permintaan Join Group Baru',
            'message' => sprintf(
                '%s mengajukan permintaan bergabung ke group "%s" pada %s.',
                $requesterName,
                $groupName,
                $joinRequest->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? now()->timezone(config('app.timezone'))->format('d M Y H:i')
            ),
            'action_url' => route('groups.detail', ['uuid' => (string) ($joinRequest->studyGroup?->uuid ?? '')]),
            'action_label' => 'Tinjau permintaan',
            'icon' => 'fi-rr-users-alt',
            'accent' => 'cyan',
            'resource' => [
                'type' => 'study_group_join_request',
                'id' => (int) $joinRequest->id,
                'study_group_id' => (int) ($joinRequest->study_group_id ?? 0),
                'study_group_uuid' => (string) ($joinRequest->studyGroup?->uuid ?? ''),
            ],
            'meta' => [
                'requester_id' => (int) ($joinRequest->user_id ?? 0),
                'requester_name' => $requesterName,
                'group_name' => $groupName,
                'requested_at' => $requestedAt,
                'reason' => $reasonSnippet,
            ],
        ]);
    }
}
