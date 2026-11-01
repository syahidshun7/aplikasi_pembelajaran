<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index(['study_group_id', 'created_at'], 'events_group_created_index');
            $table->index(['job_id', 'created_at'], 'events_job_created_index');
        });

        // The progress summary looks up a user's present attendance for a page
        // of event IDs. The existing unique key starts with event_id, so it
        // cannot efficiently serve this user-first lookup.
        Schema::table('event_attendances', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'event_id'], 'event_attendance_user_status_event_index');
        });

        // Likewise, event progress reads approved submissions by user and quest.
        Schema::table('submissions', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'quest_id'], 'submissions_user_status_quest_index');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex('submissions_user_status_quest_index');
        });

        Schema::table('event_attendances', function (Blueprint $table) {
            $table->dropIndex('event_attendance_user_status_event_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_group_created_index');
            $table->dropIndex('events_job_created_index');
        });
    }
};
