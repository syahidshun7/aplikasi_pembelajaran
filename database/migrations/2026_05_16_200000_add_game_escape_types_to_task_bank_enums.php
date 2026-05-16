<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE task_banks MODIFY assessment_type ENUM('essay', 'multiple_choice', 'mixed', 'game_escape') NOT NULL DEFAULT 'essay'");
        DB::statement("ALTER TABLE task_questions MODIFY question_type ENUM('essay', 'multiple_choice', 'game_stage') NOT NULL DEFAULT 'essay'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE task_questions MODIFY question_type ENUM('essay', 'multiple_choice') NOT NULL DEFAULT 'essay'");
        DB::statement("ALTER TABLE task_banks MODIFY assessment_type ENUM('essay', 'multiple_choice', 'mixed') NOT NULL DEFAULT 'essay'");
    }
};
