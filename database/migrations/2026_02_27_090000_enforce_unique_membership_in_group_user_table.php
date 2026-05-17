<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cleanup existing duplicates first to avoid unique index failure.
        $duplicates = DB::table('group_user')
            ->selectRaw('user_id, study_group_id, MIN(id) as keep_id, COUNT(*) as total_rows')
            ->groupBy('user_id', 'study_group_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('group_user')
                ->where('user_id', $row->user_id)
                ->where('study_group_id', $row->study_group_id)
                ->where('id', '<>', $row->keep_id)
                ->delete();
        }

        Schema::table('group_user', function (Blueprint $table) {
            $table->unique(['user_id', 'study_group_id'], 'group_user_user_id_study_group_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_user', function (Blueprint $table) {
            $table->dropUnique('group_user_user_id_study_group_id_unique');
        });
    }
};
