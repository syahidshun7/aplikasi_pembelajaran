<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dooplab_todos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('assignment_mode', 20)->default('self'); // self | mentor
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_user_id', 'is_completed'], 'dooplab_todos_owner_completed_idx');
            $table->index(['mentor_user_id', 'is_completed'], 'dooplab_todos_mentor_completed_idx');
            $table->index(['assignment_mode', 'created_at'], 'dooplab_todos_mode_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dooplab_todos');
    }
};
