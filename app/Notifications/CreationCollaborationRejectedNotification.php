<?php

namespace App\Notifications;

use App\Models\Creation;
use App\Models\User;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CreationCollaborationRejectedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Creation $creation,
        private readonly User $owner,
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
        $ownerName = (string) ($this->owner->username ?: $this->owner->name ?: 'Owner');

        return $this->buildPayload([
            'type' => 'creation',
            'category' => 'creation',
            'event' => 'collaboration_rejected',
            'title' => 'Collaboration Request Update',
            'message' => sprintf(
                'Your collaboration request for "%s" was declined by %s.',
                (string) $this->creation->title,
                $ownerName
            ),
            'action_url' => route('hall.creations.show', ['creation' => $this->creation->slug ?: (int) $this->creation->id]),
            'action_label' => 'View creation',
            'icon' => 'fi-rr-cross-circle',
            'accent' => 'amber',
            'resource' => [
                'type' => 'creation',
                'id' => (int) $this->creation->id,
                'slug' => (string) ($this->creation->slug ?? ''),
            ],
            'meta' => [
                'owner_id' => (int) $this->owner->id,
                'owner_name' => $ownerName,
                'creation_title' => (string) $this->creation->title,
                'created_at' => now()->toISOString(),
            ],
        ]);
    }
}
