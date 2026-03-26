<?php

namespace App\Services;

use App\Models\Creation;
use App\Models\CreationInsight;
use App\Models\Event;
use App\Models\Message;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\AssignmentReminderNotification;
use App\Notifications\AssignmentSubmittedNotification;
use App\Notifications\ChatMessageNotification;
use App\Notifications\CreationAppreciatedNotification;
use App\Notifications\CreationInsightAddedNotification;
use App\Notifications\EventPublishedNotification;
use App\Notifications\GradeReleasedNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class LmsNotificationService
{
    public function notifySubmissionReceived(Submission $submission): void
    {
        $recipients = $this->submissionReviewRecipients($submission);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new AssignmentSubmittedNotification($submission));
    }

    public function notifyGradeReleased(Submission $submission): void
    {
        $submission->loadMissing('user');
        if (! $submission->user) {
            return;
        }

        $submission->user->notify(new GradeReleasedNotification($submission));
    }

    public function notifyChatMessage(User|Collection|EloquentCollection $recipients, User $sender, ?Message $message = null, ?string $preview = null, ?string $room = null): void
    {
        $recipientCollection = $this->normalizeRecipients($recipients)
            ->where('id', '!=', $sender->id)
            ->values();

        if ($recipientCollection->isEmpty()) {
            return;
        }

        Notification::send($recipientCollection, new ChatMessageNotification([
            'message_id' => (int) ($message?->id ?? 0),
            'sender_id' => (int) $sender->id,
            'sender_name' => $sender->username ?: $sender->name,
            'preview' => $preview !== null ? mb_strimwidth(trim($preview), 0, 120, '...') : trim((string) ($message?->message ?? '')),
            'room' => $room ?: (string) ($message?->room ?? 'global'),
        ]));
    }

    public function notifyAssignmentReminder(User $user, Quest $quest): void
    {
        $user->notify(new AssignmentReminderNotification($quest));
    }

    public function notifyAnnouncement(User|Collection|EloquentCollection $recipients, array $announcement): void
    {
        $recipientCollection = $this->normalizeRecipients($recipients);
        if ($recipientCollection->isEmpty()) {
            return;
        }

        Notification::send($recipientCollection, new AnnouncementNotification($announcement));
    }

    public function notifyEventPublished(Event $event): void
    {
        $event->loadMissing([
            'studyGroup.users:id,name,username,email,role,job_id',
            'job:id,name',
        ]);

        $recipients = $event->studyGroup
            ? $event->studyGroup->users
                ->filter(fn (User $user) => ! $user->isStaff())
                ->values()
            : User::query()
                ->when((int) ($event->job_id ?? 0) > 0, function ($query) use ($event) {
                    $query->where('job_id', (int) $event->job_id);
                })
                ->whereNotIn('role', User::staffRoles())
                ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new EventPublishedNotification($event));
    }

    public function notifyCreationAppreciated(Creation $creation, User $actor): void
    {
        $creation->loadMissing('user:id,name,username,email');
        $owner = $creation->user;

        if (! $owner || (int) $owner->id === (int) $actor->id) {
            return;
        }

        $owner->notify(new CreationAppreciatedNotification($creation, $actor));
    }

    public function notifyCreationInsightAdded(CreationInsight $insight): void
    {
        $insight->loadMissing([
            'user:id,name,username,email',
            'creation.user:id,name,username,email',
        ]);

        $owner = $insight->creation?->user;
        $actorId = (int) ($insight->user_id ?? 0);

        if (! $owner || (int) $owner->id === $actorId) {
            return;
        }

        $owner->notify(new CreationInsightAddedNotification($insight));
    }

    private function submissionReviewRecipients(Submission $submission): Collection
    {
        $submission->loadMissing([
            'quest.studyGroup:id,job_id',
            'quest.taskBank:id,job_role_id',
        ]);

        $jobId = (int) ($submission->quest?->studyGroup?->job_id ?? $submission->quest?->taskBank?->job_role_id ?? 0);

        return User::query()
            ->where(function ($query) use ($jobId) {
                $query->whereIn('role', User::adminRoles());

                if ($jobId > 0) {
                    $query->orWhere(function ($mentorQuery) use ($jobId) {
                        $mentorQuery->where('role', User::ROLE_MENTOR)
                            ->where('job_id', $jobId);
                    });
                } else {
                    $query->orWhere('role', User::ROLE_MENTOR);
                }
            })
            ->get();
    }

    private function normalizeRecipients(User|Collection|EloquentCollection $recipients): Collection
    {
        if ($recipients instanceof User) {
            return collect([$recipients]);
        }

        return collect($recipients)
            ->filter(fn ($recipient) => $recipient instanceof User)
            ->unique(fn (User $user) => (int) $user->id)
            ->values();
    }
}
