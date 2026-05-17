<?php

namespace App\Notifications;

use App\Models\CreationCollaborationRequest;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CreationCollaborationRequestedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly CreationCollaborationRequest $request,
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
        $request = $this->request->loadMissing([
            'requester:id,name,username',
            'creation:id,title,slug',
        ]);

        $requesterName = (string) ($request->requester?->username ?: $request->requester?->name ?: 'User');

        return $this->buildPayload([
            'type' => 'creation',
            'category' => 'creation',
            'event' => 'collaboration_requested',
            'title' => 'Collaboration Request',
            'message' => sprintf(
                '%s requested to collaborate on "%s".',
                $requesterName,
                (string) ($request->creation?->title ?? 'your creation')
            ),
            'action_url' => route('hall.creations.show', ['creation' => $request->creation?->slug ?: (int) $request->creation_id]),
            'action_label' => 'Review request',
            'icon' => 'fi-rr-users',
            'accent' => 'cyan',
            'resource' => [
                'type' => 'creation_collaboration_request',
                'id' => (int) $request->id,
                'creation_id' => (int) $request->creation_id,
                'creation_slug' => (string) ($request->creation?->slug ?? ''),
            ],
            'meta' => [
                'requester_id' => (int) ($request->requester_id ?? 0),
                'requester_name' => $requesterName,
                'requested_role' => (string) ($request->requested_role ?? ''),
                'creation_title' => (string) ($request->creation?->title ?? ''),
                'creation_slug' => (string) ($request->creation?->slug ?? ''),
                'created_at' => $request->created_at?->toISOString() ?? now()->toISOString(),
            ],
        ]);
    }
}
