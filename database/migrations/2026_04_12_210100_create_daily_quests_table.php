<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_quests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('daily_quest_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('quest_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('activity_type', 64);
            $table->unsignedInteger('target_value')->default(1);
            $table->unsignedInteger('progress_value')->default(0);
            $table->unsignedInteger('reward_exp')->default(0);
            $table->unsignedInteger('reward_gold')->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('status', 32)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quest_date', 'daily_quest_definition_id'], 'daily_quests_unique_user_date_definition');
            $table->index(['user_id', 'quest_date', 'status'], 'daily_quests_user_date_status_index');
            $table->index(['activity_type', 'quest_date'], 'daily_quests_activity_date_index');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_quests');
    }
};
