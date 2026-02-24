<?php

namespace App\Console\Commands;

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
   
    // Cari quest yang statusnya masih Available tapi sudah lewat deadline
    $count = \App\Models\Quest::where('status', 'Available')
        ->whereNotNull('deadline')
        ->where('deadline', '<', now())
        ->update(['status' => 'Done']);

    if ($count > 0) {
        $this->info("MISSION_UPDATE: {$count} quests have been marked as Done (Expired).");
    }
}
}
