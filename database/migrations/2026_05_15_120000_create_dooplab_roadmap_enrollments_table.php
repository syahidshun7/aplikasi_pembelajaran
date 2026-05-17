<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dooplab_roadmap_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('roadmap_id')->constrained('dooplab_roadmaps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['roadmap_id', 'user_id'], 'dooplab_enrollments_unique_idx');
            $table->index(['user_id', 'status'], 'dooplab_enrollments_user_status_idx');
            $table->index(['mentor_user_id', 'status'], 'dooplab_enrollments_mentor_status_idx');
        });

        Schema::create('dooplab_roadmap_node_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('dooplab_roadmap_enrollments')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('dooplab_roadmap_nodes')->cascadeOnDelete();
            $table->string('status', 20)->default('locked');
            $table->text('student_note')->nullable();
            $table->text('mentor_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'node_id'], 'dooplab_node_progress_unique_idx');
            $table->index(['enrollment_id', 'status'], 'dooplab_node_progress_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dooplab_roadmap_node_progress');
        Schema::dropIfExists('dooplab_roadmap_enrollments');
    }
};

