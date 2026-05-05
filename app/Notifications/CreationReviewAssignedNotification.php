<?php

namespace App\Notifications;

use App\Models\Creation;
use App\Models\Rubric;
use App\Models\User;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CreationReviewAssignedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Creation $creation,
        private readonly User $assignedBy,
        private readonly ?Rubric $rubric = null,
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
        $creation = $this->creation->loadMissing('user:id,name,username');
        $assignerName = (string) ($this->assignedBy->username ?: $this->assignedBy->name ?: 'Admin');
        $rubricTitle = (string) ($this->rubric?->title ?? 'Rubric belum dipilih');

        return $this->buildPayload([
            'type' => 'creation_review',
            'category' => 'creation',
            'event' => 'review_assigned',
            'title' => 'Creation Review Assignment',
            'message' => sprintf(
                '%s assigned you to review "%s".',
                $assignerName,
                (string) ($creation->title ?? 'creation')
            ),
            'action_url' => route('admin.creations.preview', ['creation' => (int) $creation->id]),
            'action_label' => 'Open review workspace',
            'icon' => 'fi-rr-checklist-task-budget',
            'accent' => 'cyan',
            'resource' => [
                'type' => 'creation',
                'id' => (int) $creation->id,
            ],
            'meta' => [
                'assigner_id' => (int) $this->assignedBy->id,
                'assigner_name' => $assignerName,
                'creation_title' => (string) ($creation->title ?? ''),
                'rubric_title' => $rubricTitle,
                'created_at' => now()->toISOString(),
            ],
        ]);
    }
}

