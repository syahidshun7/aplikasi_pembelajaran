<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dooplab_roadmaps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_by_user_id', 'updated_at'], 'dooplab_roadmaps_owner_updated_idx');
            $table->index(['is_published', 'updated_at'], 'dooplab_roadmaps_publish_updated_idx');
        });

        Schema::create('dooplab_roadmap_sections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('roadmap_id')->constrained('dooplab_roadmaps')->cascadeOnDelete();
            $table->string('title', 180);
            $table->unsignedInteger('x')->default(24);
            $table->unsignedInteger('y')->default(24);
            $table->unsignedInteger('width')->default(500);
            $table->unsignedInteger('height')->default(260);
            $table->string('bg_color', 20)->default('#dbeafe');
            $table->string('text_color', 20)->default('#1e3a8a');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['roadmap_id', 'sort_order'], 'dooplab_roadmap_sections_sort_idx');
        });

        Schema::create('dooplab_roadmap_nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('roadmap_id')->constrained('dooplab_roadmaps')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('dooplab_roadmap_sections')->nullOnDelete();
            $table->string('title', 180);
            $table->unsignedInteger('x')->default(64);
            $table->unsignedInteger('y')->default(64);
            $table->unsignedInteger('width')->default(180);
            $table->unsignedInteger('height')->default(72);
            $table->string('bg_color', 20)->default('#93c5fd');
            $table->string('text_color', 20)->default('#0f172a');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['roadmap_id', 'sort_order'], 'dooplab_roadmap_nodes_sort_idx');
            $table->index(['roadmap_id', 'section_id'], 'dooplab_roadmap_nodes_section_idx');
        });

        Schema::create('dooplab_roadmap_edges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('roadmap_id')->constrained('dooplab_roadmaps')->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('dooplab_roadmap_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('dooplab_roadmap_nodes')->cascadeOnDelete();
            $table->string('stroke_color', 20)->default('#334155');
            $table->decimal('curvature', 4, 2)->default(0.35);
            $table->timestamps();

            $table->unique(['roadmap_id', 'from_node_id', 'to_node_id'], 'dooplab_roadmap_edges_unique_idx');
            $table->index(['roadmap_id', 'from_node_id'], 'dooplab_roadmap_edges_from_idx');
            $table->index(['roadmap_id', 'to_node_id'], 'dooplab_roadmap_edges_to_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dooplab_roadmap_edges');
        Schema::dropIfExists('dooplab_roadmap_nodes');
        Schema::dropIfExists('dooplab_roadmap_sections');
        Schema::dropIfExists('dooplab_roadmaps');
    }
};

