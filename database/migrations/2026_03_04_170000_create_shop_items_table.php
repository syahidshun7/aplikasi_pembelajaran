<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_gold')->default(0);
            $table->string('icon_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_stackable')->default(true);
            $table->timestamps();
        });

        DB::table('shop_items')->insert([
            'code' => 'TIME_KEY',
            'name' => 'Time Key',
            'description' => 'Gunakan item ini untuk membuka kembali quest yang sudah lewat deadline.',
            'price_gold' => 250,
            'is_active' => true,
            'is_stackable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_items');
    }
};

