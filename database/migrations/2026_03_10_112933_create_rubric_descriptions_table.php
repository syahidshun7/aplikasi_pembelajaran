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
        Schema::create('rubric_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criteria_id')->constrained('rubric_criteria')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('rubric_levels')->cascadeOnDelete();
            $table->text('description')->nullable();

            $table->unique(['criteria_id', 'level_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rubric_descriptions');
    }
};
