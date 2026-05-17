<?php

namespace App\Console\Commands;

use App\Services\DailyQuestService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateDailyQuests extends Command
{
    protected $signature = 'daily-quests:generate {--date= : Local date to generate in Y-m-d format}';

    protected $description = 'Generate daily quests for all non-staff users';

    public function handle(DailyQuestService $dailyQuestService): int
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $dateOption = trim((string) $this->option('date'));

        try {
            $targetDate = $dateOption !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d', $dateOption, $timezone)->startOfDay()
                : CarbonImmutable::now($timezone);
        } catch (\Throwable $e) {
            $this->error('Format --date harus Y-m-d.');

            return self::FAILURE;
        }

        $generatedCount = $dailyQuestService->generateDailyQuestsForAll($targetDate);

        $this->info("DAILY_QUESTS_GENERATED: {$generatedCount}");

        return self::SUCCESS;
    }
}
