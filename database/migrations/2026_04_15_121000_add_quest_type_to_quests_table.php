<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->string('quest_type', 32)
                ->default('main')
                ->after('difficulty');

            $table->index('quest_type', 'quests_quest_type_index');
        });

        DB::table('quests')
            ->whereNull('quest_type')
            ->update(['quest_type' => 'main']);
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropIndex('quests_quest_type_index');
            $table->dropColumn('quest_type');
        });
    }
};
