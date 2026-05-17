<?php

namespace App\Console\Commands;

use App\Models\Quest;
use App\Models\User;
use App\Notifications\AssignmentReminderNotification;
use App\Services\LmsNotificationService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class SendAssignmentReminderNotifications extends Command
{
    protected $signature = 'notifications:send-assignment-reminders';

    protected $description = 'Kirim pengingat assignment yang mendekati deadline ke user yang belum submit';

    public function handle(LmsNotificationService $notifications): int
    {
        $quests = Quest::query()
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [now(), now()->addDay()])
            ->with(['studyGroup.users:id,name,username,email', 'submissions:id,quest_id,user_id'])
            ->get();

        if ($quests->isEmpty()) {
            $this->info('ASSIGNMENT_REMINDERS_SENT=0');
            return self::SUCCESS;
        }

        $questUuids = $quests
            ->pluck('uuid')
            ->filter(fn ($uuid) => (string) $uuid !== '')
            ->map(fn ($uuid) => (string) $uuid)
            ->values()
            ->all();

        $sentReminderMap = $this->buildTodayReminderMap($questUuids);
        $globalRecipients = User::query()
            ->whereNotIn('role', User::staffRoles())
            ->get(['id', 'name', 'username', 'email']);

        $sent = 0;

        foreach ($quests as $quest) {
            $questUuid = (string) $quest->uuid;
            if ($questUuid === '') {
                continue;
            }

            $submittedUserIdSet = array_fill_keys(
                $quest->submissions->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
                true
            );

            $alreadySentUserIdSet = $sentReminderMap[$questUuid] ?? [];

            $recipients = $quest->studyGroup
                ? $quest->studyGroup->users
                : $globalRecipients;

            foreach ($recipients as $recipient) {
                $recipientId = (int) $recipient->id;

                if ($recipientId <= 0) {
                    continue;
                }

                if (isset($submittedUserIdSet[$recipientId]) || isset($alreadySentUserIdSet[$recipientId])) {
                    continue;
                }

                $notifications->notifyAssignmentReminder($recipient, $quest);
                $sent++;
                $alreadySentUserIdSet[$recipientId] = true;
                $sentReminderMap[$questUuid][$recipientId] = true;
            }
        }

        $this->info("ASSIGNMENT_REMINDERS_SENT={$sent}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, bool>>
     */
    private function buildTodayReminderMap(array $questUuids): array
    {
        if (empty($questUuids)) {
            return [];
        }

        $questUuidLookup = array_fill_keys($questUuids, true);
        $map = [];

        DatabaseNotification::query()
            ->where('type', AssignmentReminderNotification::class)
            ->where('notifiable_type', User::class)
            ->whereDate('created_at', today())
            ->get(['notifiable_id', 'data'])
            ->each(function (DatabaseNotification $notification) use (&$map, $questUuidLookup): void {
                $data = is_array($notification->data) ? $notification->data : [];
                $questUuid = (string) data_get($data, 'resource.uuid', '');
                if ($questUuid === '' || ! isset($questUuidLookup[$questUuid])) {
                    return;
                }

                $userId = (int) $notification->notifiable_id;
                if ($userId <= 0) {
                    return;
                }

                $map[$questUuid][$userId] = true;
            });

        return $map;
    }
}
