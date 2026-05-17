<?php

namespace App\Notifications;

use App\Models\Creation;
use App\Models\CreationReview;
use App\Models\User;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CreationReviewPublishedNotification extends Notification implements ShouldQueue
{
    use BuildsNotificationPayload, Queueable;

    public function __construct(
        private readonly Creation $creation,
        private readonly CreationReview $officialReview,
        private readonly ?User $publisher = null,
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
        $review = $this->officialReview->loadMissing('reviewer:id,name,username');
        $publisherName = (string) ($this->publisher?->username ?: $this->publisher?->name ?: 'Admin');
        $reviewerName = (string) ($review->reviewer?->username ?: $review->reviewer?->name ?: 'Mentor');

        return $this->buildPayload([
            'type' => 'creation_review',
            'category' => 'creation',
            'event' => 'review_published',
            'title' => 'Creation Review Published',
            'message' => sprintf(
                'Official review for "%s" was published by %s.',
                (string) ($creation->title ?? 'your creation'),
                $publisherName
            ),
            'action_url' => route('hall.creations.review', ['creation' => $creation->slug ?: (int) $creation->id]),
            'action_label' => 'View review result',
            'icon' => 'fi-rr-badge-check',
            'accent' => 'emerald',
            'resource' => [
                'type' => 'creation_review',
                'id' => (int) $review->id,
                'creation_id' => (int) $creation->id,
                'creation_slug' => (string) ($creation->slug ?? ''),
            ],
            'meta' => [
                'publisher_id' => (int) ($this->publisher?->id ?? 0),
                'publisher_name' => $publisherName,
                'reviewer_name' => $reviewerName,
                'score_percent' => (int) $review->score_percent,
                'status' => (string) $review->status,
                'created_at' => now()->toISOString(),
            ],
        ]);
    }
}
