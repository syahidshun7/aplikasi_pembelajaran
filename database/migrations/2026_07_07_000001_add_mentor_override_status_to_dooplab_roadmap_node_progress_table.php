<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_roadmap_node_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('dooplab_roadmap_node_progress', 'mentor_override_status')) {
                $table->string('mentor_override_status', 20)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_roadmap_node_progress', function (Blueprint $table) {
            if (Schema::hasColumn('dooplab_roadmap_node_progress', 'mentor_override_status')) {
                $table->dropColumn('mentor_override_status');
            }
        });
    }
};
