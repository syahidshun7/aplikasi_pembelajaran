<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_skins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_item_id')->nullable()->constrained('shop_items')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('preview_image_path')->nullable();
            // Theme config as individual columns for easy querying
            $table->string('hero_gradient')->default('linear-gradient(135deg, #0d1117 0%, #1a1c2c 100%)');
            $table->string('accent_color')->default('#4ed4d4');
            $table->string('border_color')->default('#3d415f');
            $table->string('glow_color')->default('rgba(78,212,212,0.2)');
            $table->string('stat_panel_bg')->default('#141b29');
            $table->string('text_primary')->default('#4ed4d4');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_profile_skin_id')
                ->nullable()
                ->after('profile_photo')
                ->constrained('profile_skins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\ProfileSkin::class, 'active_profile_skin_id');
            $table->dropColumn('active_profile_skin_id');
        });

        Schema::dropIfExists('profile_skins');
    }
};
