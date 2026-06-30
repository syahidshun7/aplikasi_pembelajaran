<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot table: logbook members (users dan mentors per logbook)
        Schema::create('dooplab_logbook_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_id')->constrained('dooplab_logbooks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // role: 'mentor' = mentor yang assign/pantau, 'member' = user yang mengisi logbook
            $table->string('role', 20)->default('member');
            $table->timestamps();

            $table->unique(['logbook_id', 'user_id'], 'dooplab_logbook_members_unique');
            $table->index(['logbook_id', 'role'], 'dooplab_logbook_members_role_idx');
        });

        // Hapus kolom lama (single mentor/assigned)
        Schema::table('dooplab_logbooks', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropIndex('dooplab_logbooks_assigned_user_idx');
            $table->dropColumn('assigned_user_id');

            $table->dropForeign(['mentor_user_id']);
            $table->dropColumn('mentor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dooplab_logbook_members');

        Schema::table('dooplab_logbooks', function (Blueprint $table) {
            $table->foreignId('mentor_user_id')->nullable()->after('owner_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->after('mentor_user_id')->constrained('users')->nullOnDelete();
        });
    }
};
