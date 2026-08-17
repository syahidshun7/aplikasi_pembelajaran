<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_content_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 50);
            $table->unsignedBigInteger('content_id');
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'content_type', 'content_id'], 'user_content_reads_unique');
            $table->index(['content_type', 'content_id'], 'user_content_reads_content_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_content_reads');
    }
};
