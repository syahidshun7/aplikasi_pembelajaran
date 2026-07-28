<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quest_attempt_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'quest_id', 'attempt_number'],
                'user_quest_attempt_session_unique'
            );
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quest_attempt_sessions');
    }
};
