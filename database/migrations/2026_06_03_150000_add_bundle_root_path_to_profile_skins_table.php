<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_skins', function (Blueprint $table) {
            $table->string('bundle_root_path')->nullable()->after('decoration_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('profile_skins', function (Blueprint $table) {
            $table->dropColumn('bundle_root_path');
        });
    }
};
