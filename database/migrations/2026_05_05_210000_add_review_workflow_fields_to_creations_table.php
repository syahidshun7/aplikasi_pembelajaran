<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creations', function (Blueprint $table) {
            $table->boolean('is_open_for_review')
                ->default(false)
                ->after('is_open_for_collaboration');

            $table->string('review_status', 30)
                ->default('none')
                ->after('is_open_for_review');

            $table->foreignId('assigned_reviewer_id')
                ->nullable()
                ->after('review_status')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_rubric_id')
                ->nullable()
                ->after('assigned_reviewer_id')
                ->constrained('rubrics')
                ->nullOnDelete();

            $table->index('is_open_for_review');
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('creations', function (Blueprint $table) {
            $table->dropIndex(['is_open_for_review']);
            $table->dropIndex(['review_status']);
            $table->dropConstrainedForeignId('assigned_rubric_id');
            $table->dropConstrainedForeignId('assigned_reviewer_id');
            $table->dropColumn(['review_status', 'is_open_for_review']);
        });
    }
};

