<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dooplab_todo_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained('dooplab_todos')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['todo_id', 'created_at'], 'dooplab_todo_notes_todo_created_idx');
            $table->index(['author_user_id', 'created_at'], 'dooplab_todo_notes_author_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dooplab_todo_notes');
    }
};
