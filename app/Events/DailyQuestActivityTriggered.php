<?php

namespace App\Events;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailyQuestActivityTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $activityType,
        public readonly int $amount = 1,
        public readonly array $context = [],
        public readonly ?CarbonInterface $occurredAt = null,
    ) {}
}
