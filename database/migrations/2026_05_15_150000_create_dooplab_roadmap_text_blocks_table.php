<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dooplab_roadmap_text_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('roadmap_id')->constrained('dooplab_roadmaps')->cascadeOnDelete();
            $table->text('content');
            $table->unsignedInteger('x')->default(120);
            $table->unsignedInteger('y')->default(120);
            $table->unsignedInteger('width')->default(320);
            $table->unsignedInteger('height')->default(120);
            $table->string('bg_color', 20)->default('transparent');
            $table->string('text_color', 20)->default('#e6f6ff');
            $table->unsignedSmallInteger('font_size')->default(16);
            $table->string('text_align', 20)->default('left');
            $table->string('text_valign', 20)->default('top');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['roadmap_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dooplab_roadmap_text_blocks');
    }
};
