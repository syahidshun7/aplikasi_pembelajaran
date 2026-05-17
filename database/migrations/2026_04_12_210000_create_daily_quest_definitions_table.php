<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_quest_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('activity_type', 64);
            $table->unsignedInteger('target_value')->default(1);
            $table->unsignedInteger('reward_exp')->default(0);
            $table->unsignedInteger('reward_gold')->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('activity_type');
        });

        DB::table('daily_quest_definitions')->insert([
            [
                'code' => 'daily_login',
                'title' => 'Login Hari Ini',
                'description' => 'Masuk ke sistem minimal 1 kali hari ini.',
                'activity_type' => 'login',
                'target_value' => 1,
                'reward_exp' => 25,
                'reward_gold' => 10,
                'sort_order' => 1,
                'is_active' => true,
                'meta' => json_encode([
                    'category' => 'engagement',
                    'icon' => 'fi fi-rr-enter',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'daily_submission',
                'title' => 'Kirim 1 Tugas',
                'description' => 'Submit minimal 1 quest atau task bank hari ini.',
                'activity_type' => 'quest_submission',
                'target_value' => 1,
                'reward_exp' => 75,
                'reward_gold' => 25,
                'sort_order' => 2,
                'is_active' => true,
                'meta' => json_encode([
                    'category' => 'learning',
                    'icon' => 'fi fi-rr-paper-plane',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'daily_event_attendance',
                'title' => 'Check-in Event',
                'description' => 'Lakukan self attendance pada event yang tersedia hari ini.',
                'activity_type' => 'event_attendance',
                'target_value' => 1,
                'reward_exp' => 50,
                'reward_gold' => 20,
                'sort_order' => 3,
                'is_active' => true,
                'meta' => json_encode([
                    'category' => 'community',
                    'icon' => 'fi fi-rr-calendar-clock',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_quest_definitions');
    }
};
