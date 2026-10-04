<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\UserContentRead;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

class EventContentProgressService
{
    public const STARTS_ON = '2026-09-25';

    public function tracksProgress(Event $event): bool
    {
        if ($event->created_at === null) {
            return false;
        }

        return $event->created_at->greaterThanOrEqualTo($this->startsAt());
    }

    /**
     * @param  iterable<Event>  $events
     * @return array<int, array<string, int|bool>>
     */
    public function summariesFor(iterable $events, int $userId): array
    {
        $events = $events instanceof EloquentCollection
            ? $events->values()
            : new EloquentCollection($events instanceof \Illuminate\Support\Collection ? $events->all() : iterator_to_array($events));
        $summaries = [];
        $tracked = $events->filter(fn (Event $event) => $this->tracksProgress($event))->values();

        if ($tracked->isEmpty() || $userId <= 0) {
            foreach ($events as $event) {
                $summaries[(int) $event->id] = $this->disabledSummary();
            }

            return $summaries;
        }

        $tracked->loadMissing([
            'guides:id',
            'quests' => fn ($query) => $query
                ->select('quests.id', 'quests.status')
                ->where('quests.status', Quest::STATUS_AVAILABLE),
        ]);

        $seenGuides = $this->seenIdSet(
            $userId,
            UserContentRead::TYPE_GUIDE,
            $tracked->flatMap(fn (Event $event) => $event->guides->pluck('id'))->all()
        );
        $approvedQuests = $this->approvedQuestIdSet(
            $userId,
            $tracked->flatMap(fn (Event $event) => $event->quests->pluck('id'))->all()
        );
        $presentEvents = $this->presentEventIdSet($userId, $tracked->pluck('id')->all());

        foreach ($events as $event) {
            $eventId = (int) $event->id;

            if (! $this->tracksProgress($event)) {
                $summaries[$eventId] = $this->disabledSummary();
                continue;
            }

            $summaries[$eventId] = $this->summaryFromCounts(
                $event->guides->count(),
                $event->guides->filter(fn ($guide) => isset($seenGuides[(int) $guide->id]))->count(),
                $event->quests->where('status', Quest::STATUS_AVAILABLE)->count(),
                $event->quests->where('status', Quest::STATUS_AVAILABLE)->filter(fn ($quest) => isset($approvedQuests[(int) $quest->id]))->count(),
                isset($presentEvents[$eventId]),
            );
        }

        return $summaries;
    }

    public function decorateEvent(Event $event, int $userId): array
    {
        $event->loadMissing(['guides', 'quests']);

        if (! $this->tracksProgress($event) || $userId <= 0) {
            $event->guides->each(function ($guide): void {
                $guide->setAttribute('opened_for_user', false);
                $guide->setAttribute('counts_toward_progress', false);
            });
            $event->quests->each(function ($quest): void {
                $quest->setAttribute('opened_for_user', false);
                $quest->setAttribute('completed_for_user', false);
                $quest->setAttribute('counts_toward_progress', false);
            });

            return $this->disabledSummary();
        }

        $activeQuests = $event->quests
            ->filter(fn ($quest) => (string) $quest->status === Quest::STATUS_AVAILABLE)
            ->values();

        $seenGuides = $this->seenIdSet(
            $userId,
            UserContentRead::TYPE_GUIDE,
            $event->guides->pluck('id')->all()
        );
        $seenQuests = $this->seenIdSet(
            $userId,
            UserContentRead::TYPE_QUEST,
            $activeQuests->pluck('id')->all()
        );
        $approvedQuests = $this->approvedQuestIdSet($userId, $activeQuests->pluck('id')->all());
        $attendanceDone = isset($this->presentEventIdSet($userId, [(int) $event->id])[(int) $event->id]);

        $event->guides->each(function ($guide) use ($seenGuides): void {
            $guide->setAttribute('counts_toward_progress', true);
            $guide->setAttribute('opened_for_user', isset($seenGuides[(int) $guide->id]));
        });
        $event->quests->each(function ($quest) use ($seenQuests, $approvedQuests): void {
            $counts = (string) $quest->status === Quest::STATUS_AVAILABLE;
            $quest->setAttribute('counts_toward_progress', $counts);
            $quest->setAttribute('opened_for_user', $counts && isset($seenQuests[(int) $quest->id]));
            $quest->setAttribute('completed_for_user', $counts && isset($approvedQuests[(int) $quest->id]));
        });

        return $this->summaryFromCounts(
            $event->guides->count(),
            $event->guides->filter(fn ($guide) => (bool) $guide->opened_for_user)->count(),
            $activeQuests->count(),
            $activeQuests->filter(fn ($quest) => (bool) $quest->completed_for_user)->count(),
            $attendanceDone,
        );
    }

    public function disabledSummary(): array
    {
        return [
            'enabled' => false,
            'total' => 0,
            'opened' => 0,
            'percent' => 0,
            'completed' => false,
            'guides_total' => 0,
            'guides_opened' => 0,
            'quests_total' => 0,
            'quests_opened' => 0,
            'attendance_done' => false,
        ];
    }

    private function startsAt(): Carbon
    {
        return Carbon::parse(self::STARTS_ON, config('app.timezone'))->startOfDay();
    }

    /**
     * @param  array<int, mixed>  $contentIds
     * @return array<int, true>
     */
    private function seenIdSet(int $userId, string $contentType, array $contentIds): array
    {
        return UserContentRead::seenContentIds($userId, $contentType, $contentIds)
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $eventIds
     * @return array<int, true>
     */
    private function presentEventIdSet(int $userId, array $eventIds): array
    {
        $ids = collect($eventIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($userId <= 0 || $ids->isEmpty()) {
            return [];
        }

        return EventAttendance::query()
            ->where('user_id', $userId)
            ->whereIn('event_id', $ids->all())
            ->where('status', 'present')
            ->pluck('event_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    private function approvedQuestIdSet(int $userId, array $questIds): array
    {
        return Submission::query()
            ->where('user_id', $userId)
            ->whereIn('quest_id', $questIds)
            ->where('status', Submission::STATUS_APPROVED)
            ->distinct()
            ->pluck('quest_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    private function summaryFromCounts(
        int $guidesTotal,
        int $guidesOpened,
        int $questsTotal,
        int $questsOpened,
        bool $attendanceDone,
    ): array {
        $total = $guidesTotal + $questsTotal + 1;
        $opened = $guidesOpened + $questsOpened + ($attendanceDone ? 1 : 0);

        return [
            'enabled' => true,
            'total' => $total,
            'opened' => $opened,
            'percent' => (int) round(($opened / $total) * 100),
            'completed' => $opened >= $total,
            'guides_total' => $guidesTotal,
            'guides_opened' => $guidesOpened,
            'quests_total' => $questsTotal,
            'quests_opened' => $questsOpened,
            'attendance_done' => $attendanceDone,
        ];
    }
}
