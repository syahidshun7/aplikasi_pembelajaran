<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_skins', function (Blueprint $table) {
            $table->string('renderer_type')->default('vue_template')->after('preview_image_path');
            $table->json('config_json')->nullable()->after('project_manifest');
        });

        DB::table('profile_skins')
            ->where('template_key', 'project_static')
            ->whereNotNull('project_entry_path')
            ->update(['renderer_type' => 'project_static']);
    }

    public function down(): void
    {
        Schema::table('profile_skins', function (Blueprint $table) {
            $table->dropColumn([
                'renderer_type',
                'config_json',
            ]);
        });
    }
};
