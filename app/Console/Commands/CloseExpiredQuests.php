<?php

namespace App\Console\Commands;

use App\Models\Quest;
use App\Support\Cache\CacheVersion;
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

    // Data recovery: legacy manual quest yang pernah ditandai Done/Completed
    // harus kembali Available agar tidak bentrok dengan mode manual + Time Key.
    $normalizedManualCount = Quest::query()
        ->where(function ($query) {
            $query->whereNull('schedule_type')
                ->orWhere('schedule_type', Quest::SCHEDULE_MANUAL);
        })
        ->whereIn('status', [Quest::STATUS_DONE, 'Completed'])
        ->update(['status' => Quest::STATUS_AVAILABLE]);

    $updatedCount += (int) $normalizedManualCount;

    if ($updatedCount > 0) {
        CacheVersion::bump('quests');
        CacheVersion::bump('home');
        $this->info("MISSION_UPDATE: {$updatedCount} quests synchronized with schedule.");
    }
}
}
