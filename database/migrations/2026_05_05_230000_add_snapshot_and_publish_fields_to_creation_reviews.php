<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creation_peer_reviews', function (Blueprint $table) {
            $table->json('rubric_snapshot')->nullable()->after('result_breakdown');
        });

        Schema::table('creation_reviews', function (Blueprint $table) {
            $table->json('rubric_snapshot')->nullable()->after('result_breakdown');
            $table->foreignId('source_peer_review_id')
                ->nullable()
                ->after('rubric_snapshot')
                ->constrained('creation_peer_reviews')
                ->nullOnDelete();
            $table->foreignId('published_by')
                ->nullable()
                ->after('source_peer_review_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_by');
        });
    }

    public function down(): void
    {
        Schema::table('creation_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropConstrainedForeignId('source_peer_review_id');
            $table->dropColumn(['published_at', 'rubric_snapshot']);
        });

        Schema::table('creation_peer_reviews', function (Blueprint $table) {
            $table->dropColumn('rubric_snapshot');
        });
    }
};

