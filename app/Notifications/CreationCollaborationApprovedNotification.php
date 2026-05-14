<?php

namespace App\Notifications;

use App\Models\Creation;
use App\Models\User;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CreationCollaborationApprovedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Creation $creation,
        private readonly User $owner,
        private readonly string $role,
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
        $ownerName = (string) ($this->owner->username ?: $this->owner->name ?: $creation->user?->username ?: $creation->user?->name ?: 'Owner');

        return $this->buildPayload([
            'type' => 'creation',
            'category' => 'creation',
            'event' => 'collaboration_approved',
            'title' => 'Collaboration Approved',
            'message' => sprintf(
                'Your collaboration request for "%s" was approved by %s.',
                (string) $creation->title,
                $ownerName
            ),
            'action_url' => route('hall.creations.show', ['creation' => $creation->slug ?: (int) $creation->id]),
            'action_label' => 'Open creation',
            'icon' => 'fi-rr-badge-check',
            'accent' => 'emerald',
            'resource' => [
                'type' => 'creation',
                'id' => (int) $creation->id,
                'slug' => (string) ($creation->slug ?? ''),
            ],
            'meta' => [
                'owner_id' => (int) $this->owner->id,
                'owner_name' => $ownerName,
                'creation_title' => (string) $creation->title,
                'role' => $this->role,
                'created_at' => now()->toISOString(),
            ],
        ]);
    }
}
