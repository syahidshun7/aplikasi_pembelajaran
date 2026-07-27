<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->string('attempt_mode', 20)->default('single')->after('quest_type');
            $table->unsignedSmallInteger('max_attempts')->nullable()->after('attempt_mode');
            $table->string('grading_attempt', 20)->default('highest')->after('max_attempts');
            $table->boolean('allow_retry_after_approved')->default(false)->after('grading_attempt');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn([
                'attempt_mode',
                'max_attempts',
                'grading_attempt',
                'allow_retry_after_approved',
            ]);
        });
    }
};
