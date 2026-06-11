<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_skins', function (Blueprint $table) {
            $table->string('template_key')->default('default')->after('preview_image_path');
            $table->string('background_image_path')->nullable()->after('template_key');
            $table->string('avatar_frame_image_path')->nullable()->after('background_image_path');
            $table->string('panel_image_path')->nullable()->after('avatar_frame_image_path');
            $table->string('decoration_image_path')->nullable()->after('panel_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('profile_skins', function (Blueprint $table) {
            $table->dropColumn([
                'template_key',
                'background_image_path',
                'avatar_frame_image_path',
                'panel_image_path',
                'decoration_image_path',
            ]);
        });
    }
};
