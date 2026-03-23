<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'task_banks',
            'task_questions',
            'rubrics',
            'rubric_criteria',
            'rubric_levels',
            'rubric_descriptions',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'task_banks',
            'task_questions',
            'rubrics',
            'rubric_criteria',
            'rubric_levels',
            'rubric_descriptions',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
