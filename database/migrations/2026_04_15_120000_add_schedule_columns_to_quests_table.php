<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->string('schedule_type', 32)
                ->default('manual')
                ->after('deadline');
            $table->dateTime('available_from')
                ->nullable()
                ->after('schedule_type');
            $table->dateTime('available_until')
                ->nullable()
                ->after('available_from');

            $table->index(['schedule_type', 'available_from'], 'quests_schedule_type_available_from_index');
            $table->index('available_until', 'quests_available_until_index');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropIndex('quests_schedule_type_available_from_index');
            $table->dropIndex('quests_available_until_index');
            $table->dropColumn([
                'schedule_type',
                'available_from',
                'available_until',
            ]);
        });
    }
};
