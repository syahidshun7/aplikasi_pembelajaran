<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_todos', function (Blueprint $table) {
            $table->foreignId('creation_id')->nullable()->after('mentor_user_id')->constrained('creations')->nullOnDelete();
            $table->string('milestone_type', 30)->default('task')->after('assignment_mode');
            $table->string('workflow_status', 30)->default('todo')->after('milestone_type');
            $table->timestamp('review_requested_at')->nullable()->after('deadline_reminded_at');
            $table->timestamp('reviewed_at')->nullable()->after('review_requested_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable()->after('reviewed_by_user_id');

            $table->index(['creation_id', 'workflow_status'], 'dooplab_todos_creation_workflow_idx');
            $table->index(['milestone_type', 'workflow_status'], 'dooplab_todos_milestone_workflow_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_todos', function (Blueprint $table) {
            $table->dropIndex('dooplab_todos_creation_workflow_idx');
            $table->dropIndex('dooplab_todos_milestone_workflow_idx');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropConstrainedForeignId('creation_id');
            $table->dropColumn([
                'milestone_type',
                'workflow_status',
                'review_requested_at',
                'reviewed_at',
                'review_note',
            ]);
        });
    }
};
