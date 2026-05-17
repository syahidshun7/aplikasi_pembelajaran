<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dooplab_roadmap_node_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('dooplab_roadmap_nodes')->cascadeOnDelete();
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['node_id', 'resource_type', 'resource_id'], 'dooplab_node_resources_unique_idx');
            $table->index(['resource_type', 'resource_id'], 'dooplab_node_resources_type_idx');
        });

        $legacyNodes = DB::table('dooplab_roadmap_nodes')
            ->whereNotNull('resource_type')
            ->whereNotNull('resource_id')
            ->select('id', 'resource_type', 'resource_id')
            ->get();

        $rows = [];
        foreach ($legacyNodes as $idx => $node) {
            $rows[] = [
                'node_id' => (int) $node->id,
                'resource_type' => (string) $node->resource_type,
                'resource_id' => (int) $node->resource_id,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($rows)) {
            DB::table('dooplab_roadmap_node_resources')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dooplab_roadmap_node_resources');
    }
};
