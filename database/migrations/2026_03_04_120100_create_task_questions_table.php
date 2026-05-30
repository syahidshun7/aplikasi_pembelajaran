<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_bank_id')->constrained('task_banks')->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('question_type', ['essay', 'multiple_choice', 'game_stage', 'platforming', 'word_match'])->default('essay');
            $table->json('options_json')->nullable();
            $table->string('answer_key')->nullable();
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_questions');
    }
};
