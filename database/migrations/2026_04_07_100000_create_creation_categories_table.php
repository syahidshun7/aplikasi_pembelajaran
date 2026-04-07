<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('creation_categories')->insert([
            ['name' => 'Engineering', 'slug' => 'engineering', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Design', 'slug' => 'design', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Product', 'slug' => 'product', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Research', 'slug' => 'research', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Writing', 'slug' => 'writing', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Marketing', 'slug' => 'marketing', 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Data', 'slug' => 'data', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other', 'slug' => 'other', 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_categories');
    }
};
