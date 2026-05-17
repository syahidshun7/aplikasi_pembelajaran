<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_roadmap_nodes', function (Blueprint $table) {
            $table->string('resource_type', 20)->nullable()->after('text_valign');
            $table->unsignedBigInteger('resource_id')->nullable()->after('resource_type');

            $table->index(['resource_type', 'resource_id'], 'dooplab_nodes_resource_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_roadmap_nodes', function (Blueprint $table) {
            $table->dropIndex('dooplab_nodes_resource_idx');
            $table->dropColumn(['resource_type', 'resource_id']);
        });
    }
};

