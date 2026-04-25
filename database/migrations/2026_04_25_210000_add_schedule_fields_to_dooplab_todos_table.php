<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_todos', function (Blueprint $table) {
            $table->timestamp('start_at')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->boolean('notify_deadline_email')->default(false);
            $table->timestamp('deadline_reminded_at')->nullable();

            $table->index(['deadline', 'is_completed'], 'dooplab_todos_deadline_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_todos', function (Blueprint $table) {
            $table->dropIndex('dooplab_todos_deadline_completed_idx');
            $table->dropColumn([
                'start_at',
                'deadline',
                'notify_deadline_email',
                'deadline_reminded_at',
            ]);
        });
    }
};

