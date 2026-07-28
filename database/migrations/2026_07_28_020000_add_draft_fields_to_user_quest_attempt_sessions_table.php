<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_quest_attempt_sessions', function (Blueprint $table) {
            $table->uuid('submission_token')->nullable()->unique()->after('attempt_number');
            $table->json('draft_answers')->nullable()->after('expires_at');
            $table->longText('draft_content')->nullable()->after('draft_answers');
            $table->timestamp('draft_saved_at')->nullable()->after('draft_content');
        });
    }

    public function down(): void
    {
        Schema::table('user_quest_attempt_sessions', function (Blueprint $table) {
            $table->dropUnique(['submission_token']);
            $table->dropColumn([
                'submission_token',
                'draft_answers',
                'draft_content',
                'draft_saved_at',
            ]);
        });
    }
};
