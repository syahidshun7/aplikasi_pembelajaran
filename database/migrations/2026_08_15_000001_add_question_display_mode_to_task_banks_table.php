<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_banks', function (Blueprint $table) {
            $table->string('question_display_mode', 20)->default('all')->after('has_time_limit');
        });
    }

    public function down(): void
    {
        Schema::table('task_banks', function (Blueprint $table) {
            $table->dropColumn('question_display_mode');
        });
    }
};
