<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsNotificationMailView;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationMailView, BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly array $announcement,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications-mail');
        $this->tries = 3;
        $this->backoff = [60, 300, 900];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return (new MailMessage())
            ->subject($payload['title'])
            ->view('emails.notifications.default', $this->notificationMailViewData($notifiable, $payload, [
                'heading' => 'ANNOUNCEMENT',
                'intro' => 'Ada pengumuman penting dari sistem pembelajaran yang perlu kamu perhatikan.',
                'panelBackground' => '#ecfeff',
                'panelBorder' => '#22d3ee',
                'panelText' => '#164e63',
                'panelBody' => $payload['message'],
                'buttonBackground' => '#22d3ee',
                'buttonBorder' => '#0891b2',
                'buttonText' => '#083344',
                'footer' => 'Cek detail pengumuman di aplikasi untuk melihat informasi lanjutan atau instruksi tambahan.',
            ]));
    }

    private function payload(): array
    {
        return $this->buildPayload([
            'type' => 'announcement',
            'category' => 'announcement',
            'event' => 'published',
            'title' => (string) ($this->announcement['title'] ?? 'Pengumuman Penting'),
            'message' => (string) ($this->announcement['message'] ?? ''),
            'action_url' => (string) ($this->announcement['action_url'] ?? route('notifications.index')),
            'action_label' => (string) ($this->announcement['action_label'] ?? 'Lihat detail'),
            'icon' => 'fi-rr-megaphone',
            'accent' => 'cyan',
            'resource' => [
                'type' => 'announcement',
                'id' => (string) ($this->announcement['id'] ?? ''),
            ],
            'meta' => is_array($this->announcement['meta'] ?? null) ? $this->announcement['meta'] : [],
        ]);
    }
}
