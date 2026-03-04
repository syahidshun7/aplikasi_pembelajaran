<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->foreignId('task_bank_id')->nullable()->after('study_group_id')->constrained('task_banks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_bank_id');
        });
    }
};
