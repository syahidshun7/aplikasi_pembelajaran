<?php

namespace App\Console\Commands;

use App\Services\DailyQuestService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ExpireDailyQuests extends Command
{
    protected $signature = 'daily-quests:expire {--now= : Local timestamp used for expiration checks}';

    protected $description = 'Expire daily quests that have passed the reset window';

    public function handle(DailyQuestService $dailyQuestService): int
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $nowOption = trim((string) $this->option('now'));

        try {
            $targetNow = $nowOption !== ''
                ? CarbonImmutable::parse($nowOption, $timezone)
                : CarbonImmutable::now($timezone);
        } catch (\Throwable $e) {
            $this->error('Format --now tidak valid.');

            return self::FAILURE;
        }

        $expiredCount = $dailyQuestService->expireStaleQuests($targetNow);

        $this->info("DAILY_QUESTS_EXPIRED: {$expiredCount}");

        return self::SUCCESS;
    }
}
