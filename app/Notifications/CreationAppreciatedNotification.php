<?php

namespace App\Notifications;

use App\Models\Creation;
use App\Models\User;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CreationAppreciatedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Creation $creation,
        private readonly User $actor,
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
        $actorName = (string) ($this->actor->username ?: $this->actor->name ?: 'Someone');

        return $this->buildPayload([
            'type' => 'creation',
            'category' => 'creation',
            'event' => 'appreciated',
            'title' => 'Creation Appreciated',
            'message' => 'Your creation received a new appreciation',
            'action_url' => route('hall.creations.show', ['creation' => $creation->slug ?: (int) $creation->id]),
            'action_label' => 'View creation',
            'icon' => 'fi-rr-heart',
            'accent' => 'emerald',
            'resource' => [
                'type' => 'creation',
                'id' => (int) $creation->id,
                'slug' => (string) ($creation->slug ?? ''),
            ],
            'meta' => [
                'actor_id' => (int) $this->actor->id,
                'actor_name' => $actorName,
                'creation_title' => (string) $creation->title,
                'created_at' => now()->toISOString(),
            ],
        ]);
    }
}
