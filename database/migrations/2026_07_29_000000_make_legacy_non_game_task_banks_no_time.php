<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyBankIds = DB::table('task_banks')
            ->whereNotIn('assessment_type', ['platforming', 'word_match'])
            ->where('created_at', '<', '2026-07-28 00:00:00')
            ->pluck('id');

        if ($legacyBankIds->isEmpty()) {
            return;
        }

        DB::table('task_banks')
            ->whereIn('id', $legacyBankIds)
            ->update(['has_time_limit' => false]);

        $legacyQuestIds = DB::table('quests')
            ->whereIn('task_bank_id', $legacyBankIds)
            ->pluck('id');

        if ($legacyQuestIds->isNotEmpty()) {
            DB::table('user_quest_attempt_sessions')
                ->whereIn('quest_id', $legacyQuestIds)
                ->whereNull('submitted_at')
                ->delete();
        }
    }

    public function down(): void
    {
        // Legacy timer values were implicit defaults, not explicit administrator choices.
    }
};
