<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_roadmap_sections', function (Blueprint $table) {
            $table->unsignedSmallInteger('font_size')->default(20)->after('text_color');
        });

        Schema::table('dooplab_roadmap_nodes', function (Blueprint $table) {
            $table->unsignedSmallInteger('font_size')->default(28)->after('text_color');
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_roadmap_nodes', function (Blueprint $table) {
            $table->dropColumn('font_size');
        });

        Schema::table('dooplab_roadmap_sections', function (Blueprint $table) {
            $table->dropColumn('font_size');
        });
    }
};

