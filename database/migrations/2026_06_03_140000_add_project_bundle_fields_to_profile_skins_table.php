<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_skins', function (Blueprint $table) {
            $table->string('project_entry_path')->nullable()->after('decoration_image_path');
            $table->string('project_root_path')->nullable()->after('project_entry_path');
            $table->json('project_manifest')->nullable()->after('project_root_path');
        });
    }

    public function down(): void
    {
        Schema::table('profile_skins', function (Blueprint $table) {
            $table->dropColumn([
                'project_entry_path',
                'project_root_path',
                'project_manifest',
            ]);
        });
    }
};
