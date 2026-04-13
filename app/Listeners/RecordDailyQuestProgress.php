<?php

namespace App\Listeners;

use App\Events\DailyQuestActivityTriggered;
use App\Services\DailyQuestService;
use Illuminate\Http\Request;

class RecordDailyQuestProgress
{
    public function __construct(
        private readonly DailyQuestService $dailyQuestService,
    ) {}

    public function handle(DailyQuestActivityTriggered $event): void
    {
        $feedback = $this->dailyQuestService->recordActivity(
            $event->userId,
            $event->activityType,
            $event->amount,
            $event->context,
            $event->occurredAt,
        );

        $request = request();
        if (! $request instanceof Request || ! $request->hasSession()) {
            return;
        }

        $updatedQuests = $feedback['updated_quests'] ?? [];
        $completedQuests = $feedback['completed_quests'] ?? [];

        if (! empty($completedQuests)) {
            $first = $completedQuests[0];

            $this->flashDailyQuestFeedback($request, [
                'kind' => 'completed',
                'title' => 'DAILY QUEST COMPLETED!',
                'text' => sprintf(
                    '%s selesai. Reward +%d EXP / +%d GOLD siap diklaim.',
                    (string) ($first['title'] ?? 'Daily Quest'),
                    (int) ($first['reward_exp'] ?? 0),
                    (int) ($first['reward_gold'] ?? 0),
                ),
                'claimable_count' => (int) ($feedback['claimable_count'] ?? 0),
                'quests' => $completedQuests,
            ]);

            return;
        }

        if (! empty($updatedQuests)) {
            $first = $updatedQuests[0];

            $this->flashDailyQuestFeedback($request, [
                'kind' => 'progress',
                'title' => 'DAILY QUEST UPDATED',
                'text' => sprintf(
                    '%s progress %d/%d.',
                    (string) ($first['title'] ?? 'Daily Quest'),
                    (int) ($first['progress'] ?? 0),
                    (int) ($first['target'] ?? 1),
                ),
                'claimable_count' => (int) ($feedback['claimable_count'] ?? 0),
                'quests' => $updatedQuests,
            ]);
        }
    }

    private function flashDailyQuestFeedback(Request $request, array $payload): void
    {
        $existing = $request->session()->get('daily_quest_feedback');

        if (is_array($existing) && ($existing['kind'] ?? '') === 'completed') {
            return;
        }

        $request->session()->flash('daily_quest_feedback', $payload);
    }
}
