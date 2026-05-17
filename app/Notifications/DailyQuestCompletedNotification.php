<?php

namespace App\Notifications;

use App\Models\DailyQuest;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DailyQuestCompletedNotification extends Notification
{
    use BuildsNotificationPayload;

    public function __construct(
        private readonly DailyQuest $dailyQuest,
    ) {}

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
        $dailyQuest = $this->dailyQuest->loadMissing('definition:id,code');

        return $this->buildPayload([
            'type' => 'daily_quest',
            'category' => 'daily_quest',
            'event' => 'completed',
            'title' => 'Daily Quest Completed',
            'message' => sprintf(
                '"%s" selesai. Reward +%d EXP / +%d GOLD siap diklaim.',
                $dailyQuest->title ?: 'Daily Quest',
                (int) ($dailyQuest->reward_exp ?? 0),
                (int) ($dailyQuest->reward_gold ?? 0),
            ),
            'action_url' => route('lobby') . '#daily-quests',
            'action_label' => 'Claim reward',
            'icon' => 'fi-rr-trophy-star',
            'accent' => 'emerald',
            'resource' => [
                'type' => 'daily_quest',
                'id' => (int) $dailyQuest->id,
                'uuid' => (string) $dailyQuest->uuid,
            ],
            'meta' => [
                'daily_quest_title' => (string) $dailyQuest->title,
                'reward_exp' => (int) ($dailyQuest->reward_exp ?? 0),
                'reward_gold' => (int) ($dailyQuest->reward_gold ?? 0),
                'definition_code' => (string) ($dailyQuest->definition?->code ?? ''),
            ],
        ]);
    }
}
