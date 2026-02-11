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
        Schema::create('quests', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('description')->nullable();
        $table->enum('difficulty', ['C-Rank', 'B-Rank', 'A-Rank', 'S-Rank'])->default('C-Rank');
        $table->integer('reward_gold')->default(0);
        $table->integer('reward_xp')->default(0);
        $table->enum('status', ['Available', 'In-Progress', 'Done'])->default('Available');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
