<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChatMessageNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly array $messageData,
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
        $sender = (string) ($this->messageData['sender_name'] ?? 'Seseorang');
        $preview = trim((string) ($this->messageData['preview'] ?? ''));
        $room = (string) ($this->messageData['room'] ?? 'global');

        return $this->buildPayload([
            'type' => 'chat',
            'category' => 'chat',
            'event' => 'message_received',
            'title' => 'Pesan Baru',
            'message' => $preview !== ''
                ? sprintf('%s mengirim pesan di room %s: %s', $sender, $room, $preview)
                : sprintf('%s mengirim pesan baru di room %s.', $sender, $room),
            'action_url' => route('notifications.index'),
            'action_label' => 'Buka notifikasi',
            'icon' => 'fi-rr-comment-alt',
            'accent' => 'fuchsia',
            'resource' => [
                'type' => 'chat',
                'room' => $room,
                'message_id' => (int) ($this->messageData['message_id'] ?? 0),
            ],
            'meta' => [
                'sender_id' => (int) ($this->messageData['sender_id'] ?? 0),
                'sender_name' => $sender,
                'preview' => $preview,
            ],
        ]);
    }
}
