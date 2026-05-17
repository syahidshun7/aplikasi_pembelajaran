<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Concerns\BuildsNotificationMailView;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventPublishedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationMailView, BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Event $event,
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
            ->subject('Event baru tersedia')
            ->view('emails.notifications.default', $this->notificationMailViewData($notifiable, $payload, [
                'heading' => 'EVENT_PUBLISHED',
                'intro' => 'Ada event baru yang sudah dipublikasikan untuk alur belajar kamu.',
                'panelBackground' => '#eff6ff',
                'panelBorder' => '#38bdf8',
                'panelText' => '#0f172a',
                'panelBody' => $payload['message'] . "\n\nSilakan cek detail event untuk melihat materi dan quest yang terhubung.",
                'buttonBackground' => '#38bdf8',
                'buttonBorder' => '#0284c7',
                'buttonText' => '#082f49',
                'footer' => 'Pastikan kamu membaca detail jadwal, audiens event, dan resource yang tersedia sebelum sesi dimulai.',
            ]));
    }

    private function payload(): array
    {
        $event = $this->event->loadMissing(['studyGroup:id,name', 'job:id,name']);
        $startsAt = $event->starts_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? 'jadwal menyusul';
        $audienceName = $event->studyGroup?->name
            ?: ($event->job?->name ? 'Job ' . $event->job->name : 'Public');

        return $this->buildPayload([
            'type' => 'event',
            'category' => 'event',
            'event' => 'published',
            'title' => 'Event Baru Tersedia',
            'message' => sprintf(
                'Event "%s" untuk %s sudah dipublikasikan. Mulai: %s.',
                $event->title ?: 'Event',
                $audienceName,
                $startsAt
            ),
            'action_url' => route('events.show', ['event' => $event->uuid]),
            'action_label' => 'Lihat event',
            'icon' => 'fi-rr-calendar-clock',
            'accent' => 'cyan',
            'resource' => [
                'type' => 'event',
                'id' => (int) $event->id,
                'uuid' => (string) $event->uuid,
            ],
            'meta' => [
                'starts_at' => $event->starts_at?->toISOString(),
                'ends_at' => $event->ends_at?->toISOString(),
                'study_group_name' => $event->studyGroup?->name,
                'job_name' => $event->job?->name,
            ],
        ]);
    }
}
