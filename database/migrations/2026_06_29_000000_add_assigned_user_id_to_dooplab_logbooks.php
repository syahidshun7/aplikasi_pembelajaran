<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_logbooks', function (Blueprint $table) {
            // Mentor membuat logbook dan meng-assign ke user spesifik ini.
            // Null = logbook milik sendiri (bukan assign dari mentor).
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->after('mentor_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('assigned_user_id', 'dooplab_logbooks_assigned_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_logbooks', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropIndex('dooplab_logbooks_assigned_user_idx');
            $table->dropColumn('assigned_user_id');
        });
    }
};
