<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_banks', function (Blueprint $table) {
            $table->integer('duration')->default(60)->after('assessment_type')->comment('Duration in seconds for each question or session');
        });
    }

    public function down(): void
    {
        Schema::table('task_banks', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
