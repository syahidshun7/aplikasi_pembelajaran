<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quest_attempt_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_item_id')->nullable()->constrained('shop_items')->nullOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->timestamp('unlocked_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'quest_id', 'attempt_number'],
                'user_quest_attempt_unlock_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quest_attempt_unlocks');
    }
};
