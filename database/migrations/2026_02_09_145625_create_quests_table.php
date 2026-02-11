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
    $table->text('description');
    $table->integer('xp_reward')->default(100);
    $table->enum('difficulty', ['Easy', 'Medium', 'Hard']);
    $table->boolean('is_completed')->default(false);
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
