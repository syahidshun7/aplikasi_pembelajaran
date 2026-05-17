<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_banks', function (Blueprint $table) {
            if (! Schema::hasColumn('task_banks', 'rubric_id')) {
                $table->foreignId('rubric_id')
                    ->nullable()
                    ->after('job_role_id')
                    ->constrained('rubrics')
                    ->nullOnDelete();
                $table->index(['rubric_id', 'is_active']);
            }
        });

        Schema::table('quests', function (Blueprint $table) {
            if (! Schema::hasColumn('quests', 'rubric_id')) {
                $table->foreignId('rubric_id')
                    ->nullable()
                    ->after('task_bank_id')
                    ->constrained('rubrics')
                    ->nullOnDelete();
                $table->index(['rubric_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            if (Schema::hasColumn('quests', 'rubric_id')) {
                $table->dropIndex(['rubric_id', 'status']);
                $table->dropConstrainedForeignId('rubric_id');
            }
        });

        Schema::table('task_banks', function (Blueprint $table) {
            if (Schema::hasColumn('task_banks', 'rubric_id')) {
                $table->dropIndex(['rubric_id', 'is_active']);
                $table->dropConstrainedForeignId('rubric_id');
            }
        });
    }
};

