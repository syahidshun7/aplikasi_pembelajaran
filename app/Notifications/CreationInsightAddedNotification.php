<?php

namespace App\Notifications;

use App\Models\CreationInsight;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CreationInsightAddedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly CreationInsight $insight,
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
        $insight = $this->insight->loadMissing([
            'user:id,name,username',
            'creation:id,title,slug',
        ]);

        $actorName = (string) ($insight->user?->username ?: $insight->user?->name ?: 'Someone');

        return $this->buildPayload([
            'type' => 'creation',
            'category' => 'creation',
            'event' => 'insight_added',
            'title' => 'Creation Insight',
            'message' => 'Someone left an insight on your creation',
            'action_url' => route('hall.creations.show', ['creation' => $insight->creation?->slug ?: (int) $insight->creation_id]),
            'action_label' => 'View insight',
            'icon' => 'fi-rr-comment-alt',
            'accent' => 'amber',
            'resource' => [
                'type' => 'creation',
                'id' => (int) $insight->creation_id,
                'insight_id' => (int) $insight->id,
                'slug' => (string) ($insight->creation?->slug ?? ''),
            ],
            'meta' => [
                'actor_id' => (int) ($insight->user_id ?? 0),
                'actor_name' => $actorName,
                'creation_title' => (string) ($insight->creation?->title ?? ''),
                'created_at' => $insight->created_at?->toISOString() ?? now()->toISOString(),
            ],
        ]);
    }
}
