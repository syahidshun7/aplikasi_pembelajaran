<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dooplab_roadmap_sections', function (Blueprint $table) {
            $table->string('text_align', 12)->default('left')->after('font_size');
            $table->string('text_valign', 12)->default('top')->after('text_align');
        });

        Schema::table('dooplab_roadmap_nodes', function (Blueprint $table) {
            $table->string('text_align', 12)->default('center')->after('font_size');
            $table->string('text_valign', 12)->default('middle')->after('text_align');
        });
    }

    public function down(): void
    {
        Schema::table('dooplab_roadmap_nodes', function (Blueprint $table) {
            $table->dropColumn(['text_align', 'text_valign']);
        });

        Schema::table('dooplab_roadmap_sections', function (Blueprint $table) {
            $table->dropColumn(['text_align', 'text_valign']);
        });
    }
};

