<?php

namespace App\Console\Commands;

use App\Models\Quest;
use Illuminate\Console\Command;

class CloseExpiredQuests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'app:close-expired-quests';
     protected $signature = 'quests:close-expired';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
   public function handle()
{
    $now = now();
    $updatedCount = 0;

    Quest::query()
        ->where('schedule_type', Quest::SCHEDULE_ONCE)
        ->where(function ($query) use ($now) {
            $query->whereNotNull('available_from')
                ->orWhereNotNull('available_until');
        })
        ->chunkById(100, function ($quests) use (&$updatedCount, $now) {
            foreach ($quests as $quest) {
                $nextStatus = $quest->resolveAutomatedStatus($now);

                if ((string) $quest->status === $nextStatus) {
                    continue;
                }

                $quest->forceFill(['status' => $nextStatus])->save();
                $updatedCount++;
            }
        });

    $deadlineCount = Quest::query()
        ->where('status', Quest::STATUS_AVAILABLE)
        ->whereNotNull('deadline')
        ->where('deadline', '<', $now)
        ->update(['status' => Quest::STATUS_DONE]);

    $updatedCount += $deadlineCount;

    if ($updatedCount > 0) {
        $this->info("MISSION_UPDATE: {$updatedCount} quests synchronized with deadline/schedule.");
    }
}
}
