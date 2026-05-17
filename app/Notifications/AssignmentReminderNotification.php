<?php

namespace App\Notifications;

use App\Models\Quest;
use App\Notifications\Concerns\BuildsNotificationMailView;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentReminderNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationMailView, BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Quest $quest,
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
            ->subject('Pengingat assignment')
            ->view('emails.notifications.default', $this->notificationMailViewData($notifiable, $payload, [
                'heading' => 'ASSIGNMENT_REMINDER',
                'intro' => 'Ada assignment yang perlu segera kamu cek agar tidak melewati deadline.',
                'panelBackground' => '#fef9c3',
                'panelBorder' => '#facc15',
                'panelText' => '#713f12',
                'panelBody' => $payload['message'] . "\n\nSegera buka quest dan selesaikan sebelum batas waktu berakhir.",
                'buttonBackground' => '#facc15',
                'buttonBorder' => '#854d0e',
                'buttonText' => '#111827',
                'footer' => 'Pengingat ini dikirim otomatis agar kamu tidak kehilangan progres assignment yang sedang berjalan.',
            ]));
    }

    private function payload(): array
    {
        $quest = $this->quest;
        $deadlineText = $quest->deadline?->timezone(config('app.timezone'))->format('d M Y H:i') ?? 'tanpa deadline';

        return $this->buildPayload([
            'type' => 'assignment',
            'category' => 'assignment',
            'event' => 'reminder',
            'title' => 'Pengingat Assignment',
            'message' => sprintf(
                'Assignment "%s" akan jatuh tempo pada %s.',
                $quest->title ?: 'Quest',
                $deadlineText
            ),
            'action_url' => route('quests.show', $quest),
            'action_label' => 'Kerjakan sekarang',
            'icon' => 'fi-rr-alarm-clock',
            'accent' => 'amber',
            'resource' => [
                'type' => 'quest',
                'id' => (int) $quest->id,
                'uuid' => (string) $quest->uuid,
            ],
            'meta' => [
                'deadline' => $quest->deadline?->toISOString(),
            ],
        ]);
    }
}
