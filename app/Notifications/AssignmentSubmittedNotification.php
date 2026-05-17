<?php

namespace App\Notifications;

use App\Models\Submission;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AssignmentSubmittedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Submission $submission,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        $submission = $this->submission->loadMissing([
            'user:id,name,username',
            'quest:id,uuid,title',
        ]);

        return $this->buildPayload([
            'type' => 'assignment',
            'category' => 'assignment',
            'event' => 'submitted',
            'title' => 'Submission Baru',
            'message' => sprintf(
                '%s mengirim assignment "%s" dan menunggu review.',
                $submission->user?->username ?: $submission->user?->name ?: 'Siswa',
                $submission->quest?->title ?: 'Quest'
            ),
            'action_url' => route('admin.submissions.inspect', $submission),
            'action_label' => 'Review sekarang',
            'icon' => 'fi-rr-file-check',
            'accent' => 'amber',
            'resource' => [
                'type' => 'submission',
                'id' => (int) $submission->id,
                'uuid' => (string) $submission->uuid,
            ],
            'meta' => [
                'submission_status' => (string) $submission->status,
                'student_id' => (int) ($submission->user_id ?? 0),
                'quest_uuid' => (string) ($submission->quest?->uuid ?? ''),
            ],
        ]);
    }
}
