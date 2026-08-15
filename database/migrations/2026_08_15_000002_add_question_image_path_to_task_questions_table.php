<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_questions', function (Blueprint $table) {
            $table->string('question_image_path')->nullable()->after('question_text');
        });
    }

    public function down(): void
    {
        Schema::table('task_questions', function (Blueprint $table) {
            $table->dropColumn('question_image_path');
        });
    }
};
