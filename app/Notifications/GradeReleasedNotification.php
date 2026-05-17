<?php

namespace App\Notifications;

use App\Models\Submission;
use App\Notifications\Concerns\BuildsNotificationMailView;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeReleasedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationMailView, BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Submission $submission,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
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
            ->subject('Nilai assignment sudah tersedia')
            ->view('emails.notifications.default', $this->notificationMailViewData($notifiable, $payload, [
                'heading' => 'GRADE_RELEASED',
                'intro' => 'Feedback dan hasil penilaian assignment kamu sudah tersedia di dashboard pembelajaran.',
                'panelBackground' => '#ecfdf5',
                'panelBorder' => '#10b981',
                'panelText' => '#065f46',
                'panelBody' => $payload['message'] . "\n\nSilakan cek feedback mentor atau guru pada halaman submission.",
                'buttonBackground' => '#10b981',
                'buttonBorder' => '#047857',
                'buttonText' => '#052e2b',
                'footer' => 'Buka halaman submission untuk melihat catatan evaluator dan status terbaru assignment kamu.',
            ]));
    }

    private function payload(): array
    {
        $submission = $this->submission->loadMissing('quest:id,uuid,title');

        return $this->buildPayload([
            'type' => 'grade',
            'category' => 'grade',
            'event' => 'released',
            'title' => 'Nilai Sudah Dirilis',
            'message' => sprintf(
                'Assignment "%s" telah dinilai. Status: %s, skor: %d.',
                $submission->quest?->title ?: 'Quest',
                $submission->status ?: 'Processed',
                (int) ($submission->grade ?? 0)
            ),
            'action_url' => route('submissions.show', ['submission' => $submission->uuid]),
            'action_label' => 'Lihat feedback',
            'icon' => 'fi-rr-badge-check',
            'accent' => 'emerald',
            'resource' => [
                'type' => 'submission',
                'id' => (int) $submission->id,
                'uuid' => (string) $submission->uuid,
            ],
            'meta' => [
                'grade' => (int) ($submission->grade ?? 0),
                'status' => (string) ($submission->status ?? ''),
                'quest_uuid' => (string) ($submission->quest?->uuid ?? ''),
            ],
        ]);
    }
}
