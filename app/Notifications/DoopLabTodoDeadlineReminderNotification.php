<?php

namespace App\Notifications;

use App\Models\DoopLabTodo;
use App\Notifications\Concerns\BuildsNotificationMailView;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoopLabTodoDeadlineReminderNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationMailView, BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly DoopLabTodo $todo,
        private readonly bool $sendEmail = false,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications-mail');
        $this->tries = 3;
        $this->backoff = [60, 300, 900];
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->sendEmail) {
            $channels[] = 'mail';
        }

        return $channels;
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
            ->subject('Pengingat deadline To-Do DoopLab')
            ->view('emails.notifications.default', $this->notificationMailViewData($notifiable, $payload, [
                'heading' => 'DOOPLAB_DEADLINE_REMINDER',
                'intro' => 'Deadline to-do DoopLab kamu sudah dekat. Segera cek agar progres tidak tertinggal.',
                'panelBackground' => '#fee2e2',
                'panelBorder' => '#ef4444',
                'panelText' => '#7f1d1d',
                'panelBody' => $payload['message'],
                'buttonBackground' => '#ef4444',
                'buttonBorder' => '#991b1b',
                'buttonText' => '#ffffff',
                'footer' => 'Pengingat ini dikirim karena opsi email deadline diaktifkan pada to-do.',
            ]));
    }

    private function payload(): array
    {
        $todo = $this->todo;
        $deadlineText = $todo->deadline?->timezone(config('app.timezone'))->format('d M Y H:i') ?? 'tanpa deadline';

        return $this->buildPayload([
            'type' => 'dooplab_todo',
            'category' => 'dooplab',
            'event' => 'deadline_reminder',
            'title' => 'Pengingat Deadline To-Do',
            'message' => sprintf(
                'To-do "%s" akan jatuh tempo pada %s.',
                $todo->title ?: 'To-Do DoopLab',
                $deadlineText
            ),
            'action_url' => route('dooplab.dashboard'),
            'action_label' => 'Buka DoopLab',
            'icon' => 'fi-rr-alarm-clock',
            'accent' => 'danger',
            'resource' => [
                'type' => 'dooplab_todo',
                'id' => (int) $todo->id,
                'uuid' => (string) $todo->uuid,
            ],
            'meta' => [
                'deadline' => $todo->deadline?->toISOString(),
                'notify_deadline_email' => $this->sendEmail,
            ],
        ]);
    }
}

