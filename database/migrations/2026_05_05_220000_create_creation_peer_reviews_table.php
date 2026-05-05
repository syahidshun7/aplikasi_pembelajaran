<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_peer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rubric_id')->constrained('rubrics')->cascadeOnDelete();
            $table->unsignedTinyInteger('score_percent');
            $table->string('status', 30)->default('approved');
            $table->text('feedback')->nullable();
            $table->json('selected_levels');
            $table->json('result_breakdown');
            $table->timestamp('reviewed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['creation_id', 'reviewer_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_peer_reviews');
    }
};

