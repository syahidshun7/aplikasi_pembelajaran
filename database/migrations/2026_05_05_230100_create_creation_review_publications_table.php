<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_review_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('peer_review_id')
                ->nullable()
                ->constrained('creation_peer_reviews')
                ->nullOnDelete();
            $table->foreignId('official_review_id')
                ->nullable()
                ->constrained('creation_reviews')
                ->nullOnDelete();
            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();

            $table->index(['creation_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_review_publications');
    }
};

